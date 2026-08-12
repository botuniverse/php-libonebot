<?php

declare(strict_types=1);

namespace Tests\OneBot\V12\Action;

use OneBot\Util\FileUtil;
use OneBot\V12\Action\ActionHandlerBase;
use OneBot\V12\Action\DefaultActionHandler;
use OneBot\V12\Object\Action;
use OneBot\V12\OneBot;
use OneBot\V12\RetCode;
use PHPUnit\Framework\TestCase;

/**
 * 测试上传路径限制与分片上传的状态上限
 *
 * @internal
 */
class ActionHandlerBaseSecurityTest extends TestCase
{
    /** @var DefaultActionHandler */
    private static $handler;

    /** @var string 测试用的上传目录 */
    private static $upload_dir;

    /** @var string 恢复用的原上传目录配置 */
    private static $origin_upload_dir;

    public static function setUpBeforeClass(): void
    {
        self::$handler = new DefaultActionHandler();
        self::$origin_upload_dir = ob_config('file_upload.path');
        self::$upload_dir = sys_get_temp_dir() . '/ob-test-upload-' . uniqid();
        OneBot::getInstance()->getConfig()->set('file_upload.path', self::$upload_dir);
    }

    public static function tearDownAfterClass(): void
    {
        OneBot::getInstance()->getConfig()->set('file_upload.path', self::$origin_upload_dir);
        FileUtil::removeDirRecursive(self::$upload_dir);
    }

    public function tearDown(): void
    {
        // 清理分片上传的静态缓存，避免影响其他测试
        self::setUploadFragment([]);
    }

    public function testUploadFilePathInsideUploadDir()
    {
        FileUtil::mkdir(self::$upload_dir, 0755, true);
        $inside_file = FileUtil::getRealPath(self::$upload_dir . '/inside.txt');
        file_put_contents($inside_file, 'inside content');
        $resp = self::$handler->onUploadFile(new Action('upload_file', [
            'type' => 'path',
            'name' => 'inside.txt',
            'path' => $inside_file,
        ]), ONEBOT_JSON);
        $this->assertEquals(RetCode::OK, $resp->retcode);
        $this->assertArrayHasKey('file_id', $resp->data);
        [$meta, $content] = FileUtil::getMetaFile(self::$upload_dir, $resp->data['file_id']);
        $this->assertEquals('inside content', $content);
        unlink($inside_file);
    }

    public function testUploadFilePathOutsideUploadDirRejected()
    {
        // 上传目录之外的文件（如系统敏感文件）必须被拒绝
        $resp = self::$handler->onUploadFile(new Action('upload_file', [
            'type' => 'path',
            'name' => 'outside.txt',
            'path' => __FILE__,
        ]), ONEBOT_JSON);
        $this->assertNotEquals(RetCode::OK, $resp->retcode);
    }

    public function testUploadFilePathNonexistentRejected()
    {
        $resp = self::$handler->onUploadFile(new Action('upload_file', [
            'type' => 'path',
            'name' => 'notexist.txt',
            'path' => FileUtil::getRealPath(self::$upload_dir . '/not-exist-file.txt'),
        ]), ONEBOT_JSON);
        $this->assertNotEquals(RetCode::OK, $resp->retcode);
    }

    public function testUploadFilePathSymlinkOutsideRejected()
    {
        // 通过符号链接指向目录外文件也必须被拒绝（realpath 会解析符号链接）
        if (!function_exists('symlink')) {
            $this->markTestSkipped('symlink is not available');
        }
        FileUtil::mkdir(self::$upload_dir, 0755, true);
        $outside_file = tempnam(sys_get_temp_dir(), 'ob-outside-');
        file_put_contents($outside_file, 'secret');
        $link = FileUtil::getRealPath(self::$upload_dir . '/evil-link.txt');
        if (!@symlink($outside_file, $link)) {
            $this->markTestSkipped('cannot create symlink');
        }
        $resp = self::$handler->onUploadFile(new Action('upload_file', [
            'type' => 'path',
            'name' => 'evil.txt',
            'path' => $link,
        ]), ONEBOT_JSON);
        $this->assertNotEquals(RetCode::OK, $resp->retcode);
        unlink($link);
        unlink($outside_file);
    }

    public function testUploadFileFragmentedPrepareLimit()
    {
        $ok_count = 0;
        // 先准备 100 个，应该全部成功
        for ($i = 0; $i < 100; ++$i) {
            $prepare = self::$handler->onUploadFileFragmented(new Action('upload_file_fragmented', [
                'stage' => 'prepare',
                'name' => 'a' . $i . '.txt',
                'total_size' => 10,
            ]));
            if ($prepare->retcode === RetCode::OK) {
                ++$ok_count;
            }
        }
        $this->assertEquals(100, $ok_count);
        // 第 101 个必须被拒绝
        $prepare = self::$handler->onUploadFileFragmented(new Action('upload_file_fragmented', [
            'stage' => 'prepare',
            'name' => 'overflow.txt',
            'total_size' => 10,
        ]));
        $this->assertNotEquals(RetCode::OK, $prepare->retcode);
    }

    public function testUploadFileFragmentedTransferExpired()
    {
        $prepare = self::$handler->onUploadFileFragmented(new Action('upload_file_fragmented', [
            'stage' => 'prepare',
            'name' => 'expired.txt',
            'total_size' => 10,
        ]));
        $this->assertEquals(RetCode::OK, $prepare->retcode);
        $file_id = $prepare->data['file_id'];
        // 把缓存条目的时间戳改到 10 分钟之前，模拟过期
        $fragments = self::getUploadFragment();
        $fragments[$file_id]['time'] = time() - 601;
        self::setUploadFragment($fragments);
        $transfer = self::$handler->onUploadFileFragmented(new Action('upload_file_fragmented', [
            'stage' => 'transfer',
            'file_id' => $file_id,
            'offset' => 0,
            'data' => base64_encode('1234567890'),
        ]), ONEBOT_JSON);
        $this->assertNotEquals(RetCode::OK, $transfer->retcode);
        // 过期条目应已被清理
        $this->assertArrayNotHasKey($file_id, self::getUploadFragment());
    }

    public function testUploadFileFragmentedFinishExpired()
    {
        $prepare = self::$handler->onUploadFileFragmented(new Action('upload_file_fragmented', [
            'stage' => 'prepare',
            'name' => 'expired2.txt',
            'total_size' => 10,
        ]));
        $this->assertEquals(RetCode::OK, $prepare->retcode);
        $file_id = $prepare->data['file_id'];
        $fragments = self::getUploadFragment();
        $fragments[$file_id]['time'] = time() - 601;
        self::setUploadFragment($fragments);
        $finish = self::$handler->onUploadFileFragmented(new Action('upload_file_fragmented', [
            'stage' => 'finish',
            'file_id' => $file_id,
            'sha256' => hash('sha256', '1234567890'),
        ]));
        $this->assertNotEquals(RetCode::OK, $finish->retcode);
        $this->assertArrayNotHasKey($file_id, self::getUploadFragment());
    }

    public function testUploadFileFragmentedExpiredCleanedOnPrepare()
    {
        // prepare 一个分片，改旧时间戳后，再次 prepare 时应被清理掉
        $prepare = self::$handler->onUploadFileFragmented(new Action('upload_file_fragmented', [
            'stage' => 'prepare',
            'name' => 'expired3.txt',
            'total_size' => 10,
        ]));
        $file_id = $prepare->data['file_id'];
        $fragments = self::getUploadFragment();
        $fragments[$file_id]['time'] = time() - 601;
        self::setUploadFragment($fragments);
        self::$handler->onUploadFileFragmented(new Action('upload_file_fragmented', [
            'stage' => 'prepare',
            'name' => 'new.txt',
            'total_size' => 10,
        ]));
        $this->assertArrayNotHasKey($file_id, self::getUploadFragment());
    }

    public function testUploadFileFragmentedTransferRefreshesExpiryTime()
    {
        $prepare = self::$handler->onUploadFileFragmented(new Action('upload_file_fragmented', [
            'stage' => 'prepare',
            'name' => 'slow.txt',
            'total_size' => 20,
        ]));
        $this->assertEquals(RetCode::OK, $prepare->retcode);
        $file_id = $prepare->data['file_id'];
        // 模拟一个即将过期（599 秒前写入）的分片：单次 transfer 成功应刷新过期时间
        $fragments = self::getUploadFragment();
        $fragments[$file_id]['time'] = time() - 599;
        self::setUploadFragment($fragments);
        $transfer = self::$handler->onUploadFileFragmented(new Action('upload_file_fragmented', [
            'stage' => 'transfer',
            'file_id' => $file_id,
            'offset' => 0,
            'data' => base64_encode('1234567890'),
        ]), ONEBOT_JSON);
        $this->assertEquals(RetCode::OK, $transfer->retcode);
        // 时间应被刷新为当前时间，而不是停留在 prepare 时的值
        $fragments = self::getUploadFragment();
        $this->assertTrue(time() - $fragments[$file_id]['time'] < 10, 'transfer 成功后过期时间应被刷新');
        // 再次模拟接近过期（连续 transfer 之间间隔接近 600 秒），仍不应被拒绝
        $fragments[$file_id]['time'] = time() - 599;
        self::setUploadFragment($fragments);
        $transfer2 = self::$handler->onUploadFileFragmented(new Action('upload_file_fragmented', [
            'stage' => 'transfer',
            'file_id' => $file_id,
            'offset' => 10,
            'data' => base64_encode('0987654321'),
        ]), ONEBOT_JSON);
        $this->assertEquals(RetCode::OK, $transfer2->retcode);
        // 全程慢速传输完成后，finish 应成功而不是被判定过期
        $finish = self::$handler->onUploadFileFragmented(new Action('upload_file_fragmented', [
            'stage' => 'finish',
            'file_id' => $file_id,
            'sha256' => hash('sha256', '12345678900987654321'),
        ]));
        $this->assertEquals(RetCode::OK, $finish->retcode);
    }

    private static function getUploadFragment(): array
    {
        $prop = new \ReflectionProperty(ActionHandlerBase::class, 'upload_fragment');
        if (PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }
        return $prop->getValue();
    }

    private static function setUploadFragment(array $value): void
    {
        $prop = new \ReflectionProperty(ActionHandlerBase::class, 'upload_fragment');
        if (PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }
        $prop->setValue(null, $value);
    }
}
