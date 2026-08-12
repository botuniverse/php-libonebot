<?php

declare(strict_types=1);

namespace Tests\OneBot\V12;

use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\RetCode;
use OneBot\V12\Validator;
use PHPUnit\Framework\TestCase;

/**
 * 测试 validateHttpUrl 的内网地址拦截（防 SSRF）
 *
 * @internal
 */
class ValidatorTest extends TestCase
{
    public function testValidateHttpUrlPrivateIpRejected(): void
    {
        $private_urls = [
            'http://127.0.0.1/',
            'http://127.0.0.1:8080/path',
            'http://10.0.0.1/',
            'http://10.255.255.255/',
            'http://172.16.0.1/',
            'http://172.31.255.255/',
            'http://192.168.1.1/',
            'http://169.254.0.1/',
            'http://0.0.0.0/',
            'http://100.64.0.1/',
            'http://[::1]/',
            'http://[fc00::1]/',
            'http://[fe80::1]/',
            'http://[::ffff:127.0.0.1]/',
            'http://[::ffff:192.168.1.1]/',
        ];
        foreach ($private_urls as $url) {
            try {
                Validator::validateHttpUrl($url);
                $this->fail('Expected OneBotFailureException for URL: ' . $url);
            } catch (OneBotFailureException $e) {
                $this->assertEquals(RetCode::NETWORK_ERROR, $e->getRetCode(), 'URL: ' . $url);
            }
        }
    }

    public function testValidateHttpUrlPublicIpPasses(): void
    {
        Validator::validateHttpUrl('http://8.8.8.8/');
        Validator::validateHttpUrl('https://1.1.1.1/');
        // ::ffff:0:0/96 之外、看起来像 IPv4 映射的合法全局地址不应被误判为私网
        Validator::validateHttpUrl('http://[0:0:0:0:1:ffff:7f00:1]/');
        Validator::validateHttpUrl('http://[::ffff:8.8.8.8]/');
        $this->assertTrue(true);
    }

    public function testValidateHttpUrlPublicHostnamePassesWithResolvableDns(): void
    {
        // 域名解析到公网 IP 时必须放行（DNS 结果可注入，不依赖真实网络）
        TestableValidator::$dns_records = ['example.com' => [
            ['host' => 'example.com', 'type' => 'A', 'ip' => '8.8.8.8', 'ttl' => 300, 'class' => 'IN'],
            ['host' => 'example.com', 'type' => 'AAAA', 'ipv6' => '2606:4700::1111', 'ttl' => 300, 'class' => 'IN'],
        ]];
        TestableValidator::validateHttpUrl('https://example.com/');
        $this->assertTrue(true);
    }

    public function testValidateHttpUrlDnsFailureRejected(): void
    {
        // DNS 查询失败（返回 false 或无任何记录）时 fail-closed：必须拒绝
        TestableValidator::$dns_records = [];
        try {
            TestableValidator::validateHttpUrl('https://unresolvable.invalid/');
            $this->fail('Expected OneBotFailureException for unresolvable host');
        } catch (OneBotFailureException $e) {
            $this->assertEquals(RetCode::NETWORK_ERROR, $e->getRetCode());
        }
        // 返回空记录数组同样拒绝
        TestableValidator::$dns_records = ['unresolvable.invalid' => []];
        try {
            TestableValidator::validateHttpUrl('https://unresolvable.invalid/');
            $this->fail('Expected OneBotFailureException for host without records');
        } catch (OneBotFailureException $e) {
            $this->assertEquals(RetCode::NETWORK_ERROR, $e->getRetCode());
        }
    }

    public function testValidateHttpUrlDnsRecordWithIpv6KeyRejected(): void
    {
        // dns_get_record 对 AAAA 记录返回的键是 ipv6，仅解析到内网 IPv6 的域名必须被拦截
        TestableValidator::$dns_records = ['internal-v6.example' => [
            ['host' => 'internal-v6.example', 'type' => 'AAAA', 'ipv6' => '::1', 'ttl' => 300, 'class' => 'IN'],
        ]];
        try {
            TestableValidator::validateHttpUrl('https://internal-v6.example/');
            $this->fail('Expected OneBotFailureException for IPv6-only private host');
        } catch (OneBotFailureException $e) {
            $this->assertEquals(RetCode::NETWORK_ERROR, $e->getRetCode());
        }
        // fc00::/7 唯一本地地址同样拦截
        TestableValidator::$dns_records = ['internal-v6.example' => [
            ['host' => 'internal-v6.example', 'type' => 'AAAA', 'ipv6' => 'fc00::1', 'ttl' => 300, 'class' => 'IN'],
        ]];
        try {
            TestableValidator::validateHttpUrl('https://internal-v6.example/');
            $this->fail('Expected OneBotFailureException for ULA IPv6 host');
        } catch (OneBotFailureException $e) {
            $this->assertEquals(RetCode::NETWORK_ERROR, $e->getRetCode());
        }
        // 公网 IPv6 域名放行
        TestableValidator::$dns_records = ['public-v6.example' => [
            ['host' => 'public-v6.example', 'type' => 'AAAA', 'ipv6' => '2606:4700::1111', 'ttl' => 300, 'class' => 'IN'],
        ]];
        TestableValidator::validateHttpUrl('https://public-v6.example/');
        $this->assertTrue(true);
    }

    public function testExtractIpsFromDnsRecords(): void
    {
        $ips = TestableValidator::extractIpsFromDnsRecordsPublic([
            ['host' => 'example.com', 'type' => 'A', 'ip' => '8.8.8.8', 'ttl' => 300, 'class' => 'IN'],
            ['host' => 'example.com', 'type' => 'AAAA', 'ipv6' => '::1', 'ttl' => 300, 'class' => 'IN'],
        ]);
        $this->assertContains('8.8.8.8', $ips);
        $this->assertContains('::1', $ips);
        // 无效的 ip/ipv6 值应被忽略
        $ips = TestableValidator::extractIpsFromDnsRecordsPublic([
            ['host' => 'example.com', 'type' => 'CNAME', 'target' => 'other.example.com'],
            ['host' => 'example.com', 'type' => 'A', 'ip' => 'not-an-ip'],
            ['host' => 'example.com', 'type' => 'AAAA', 'ipv6' => 'not-an-ip'],
        ]);
        $this->assertSame([], $ips);
    }

    public function testValidateHttpUrlInvalidHostRejected(): void
    {
        $invalid_urls = [
            'http:///path',
            'https://[]/',
            'http://exa mple.com/',
            'http://exa%20mple.com/',
        ];
        foreach ($invalid_urls as $url) {
            try {
                Validator::validateHttpUrl($url);
                $this->fail('Expected OneBotFailureException for URL: ' . $url);
            } catch (OneBotFailureException $e) {
                $this->assertEquals(RetCode::NETWORK_ERROR, $e->getRetCode(), 'URL: ' . $url);
            }
        }
    }

    public function testValidateHttpUrlIpConfusionRejected(): void
    {
        // 十进制/短横线/八进制/十六进制等 IP 混淆形式，FILTER_VALIDATE_IP 与 DNS 都无法识别，必须拒绝
        $confusion_urls = [
            'http://2130706433/',
            'http://127.1/',
            'http://127.0.1/',
            'http://0177.0.0.1/',
            'http://0x7f000001/',
            'http://0x7f.0.0.1/',
            'http://999.999.999.999/',
        ];
        foreach ($confusion_urls as $url) {
            try {
                Validator::validateHttpUrl($url);
                $this->fail('Expected OneBotFailureException for URL: ' . $url);
            } catch (OneBotFailureException $e) {
                $this->assertEquals(RetCode::NETWORK_ERROR, $e->getRetCode(), 'URL: ' . $url);
            }
        }
    }

    public function testValidateHttpUrlNonHttpSchemeRejected(): void
    {
        $invalid_urls = [
            'ftp://example.com/file',
            'file:///etc/passwd',
            'javascript:alert(1)',
            'http:no-host',
            'https://',
        ];
        foreach ($invalid_urls as $url) {
            try {
                Validator::validateHttpUrl($url);
                $this->fail('Expected OneBotFailureException for URL: ' . $url);
            } catch (OneBotFailureException $e) {
                $this->assertEquals(RetCode::NETWORK_ERROR, $e->getRetCode(), 'URL: ' . $url);
            }
        }
    }

    public function testIsPrivateOrReservedIp(): void
    {
        // IPv4 私网/保留
        $private = [
            '127.0.0.1',
            '10.0.0.1',
            '172.16.0.1',
            '192.168.1.1',
            '169.254.0.1',
            '0.0.0.0',
            '100.64.0.1',
            '224.0.0.1',
            '240.0.0.1',
        ];
        foreach ($private as $ip) {
            $this->assertTrue(Validator::isPrivateOrReservedIp($ip), $ip . ' should be private/reserved');
        }
        $public = [
            '8.8.8.8',
            '1.1.1.1',
            '9.9.9.9',
        ];
        foreach ($public as $ip) {
            $this->assertFalse(Validator::isPrivateOrReservedIp($ip), $ip . ' should be public');
        }
        // IPv6 私网/保留
        $private_v6 = [
            '::1',
            '::',
            'fc00::1',
            'fd12:3456:789a::1',
            'fe80::1',
            '::ffff:127.0.0.1',
            '::ffff:192.168.1.1',
        ];
        foreach ($private_v6 as $ip) {
            $this->assertTrue(Validator::isPrivateOrReservedIp($ip), $ip . ' should be private/reserved');
        }
        $public_v6 = [
            '2606:4700::1111',
            '2001:4860:4860::8888',
            '::ffff:8.8.8.8',
            '0:0:0:0:1:ffff:7f00:1',
        ];
        foreach ($public_v6 as $ip) {
            $this->assertFalse(Validator::isPrivateOrReservedIp($ip), $ip . ' should be public');
        }
        // 非法输入
        $this->assertFalse(Validator::isPrivateOrReservedIp('not-an-ip'));
    }
}

/**
 * 可注入 DNS 查询结果的 Validator 子类，用于离线单测
 *
 * @internal
 */
class TestableValidator extends Validator
{
    /** @var array host => dns_get_record 返回的记录数组 */
    public static array $dns_records = [];

    public static function extractIpsFromDnsRecordsPublic(array $records): array
    {
        return static::extractIpsFromDnsRecords($records);
    }

    protected static function lookupHostIps(string $host): array
    {
        if (!isset(self::$dns_records[$host])) {
            // 模拟 DNS 查询失败
            return [];
        }
        return static::extractIpsFromDnsRecords(self::$dns_records[$host]);
    }
}
