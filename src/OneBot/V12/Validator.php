<?php

declare(strict_types=1);

namespace OneBot\V12;

use OneBot\Util\Utils;
use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Exception\OneBotFailureException;
use OneBot\V12\Object\Action;

class Validator
{
    /**
     * 验证传入的消息段是否合法
     * @param  array|mixed            $message
     * @throws OneBotFailureException
     */
    public static function validateMessageSegment($message): void
    {
        if (!is_array($message)) {
            throw new OneBotFailureException(RetCode::BAD_SEGMENT_DATA);
        }
        foreach ($message as $v) {
            if (!isset($v['type']) || !isset($v['data'])) {
                throw new OneBotFailureException(RetCode::BAD_SEGMENT_DATA);
            }
            if ($v['type'] === 'text' && !is_string($v['data']['text'] ?? null)) {
                throw new OneBotFailureException(RetCode::BAD_SEGMENT_DATA);
            }
            if ($v['type'] === 'image' && !isset($v['data']['file_id'])) {
                throw new OneBotFailureException(RetCode::BAD_SEGMENT_DATA);
            }
        }
    }

    /**
     * 用于验证动作对象中的参数验证
     *
     * 如果验证失败，直接抛出 BAD_PARAM 异常。
     *
     * $array 为验证方式，目前支持两种验证：
     * 1. 如果 k => true，则验证 param 是否存在 k。
     * 2. 如果 k => {list}，则在 1 的基础上验证参数 k 是否是给定 list 中的一种。
     * 3. 如果 k => int, 则根据 int 对应规则进行验证。
     *
     * @throws OneBotFailureException
     */
    public static function validateParamsByAction(Action $action_obj, array $array): void
    {
        $valid = true;
        foreach ($array as $k => $v) {
            if (!($valid = self::validateExist($action_obj, $k))) {
                break;
            }
            if ($v === true) {
                continue;
            }
            if (is_int($v)) {
                switch ($v) {
                    case ONEBOT_TYPE_ANY:
                        continue 2;
                    case ONEBOT_TYPE_STRING:
                        $func_name = 'is_string';
                        break;
                    case ONEBOT_TYPE_INT:
                        $func_name = 'is_int';
                        break;
                    case ONEBOT_TYPE_ARRAY:
                        $func_name = 'is_array';
                        break;
                    case ONEBOT_TYPE_FLOAT:
                        $func_name = 'is_float';
                        break;
                    case ONEBOT_TYPE_OBJECT:
                        $func_name = 'is_object';
                        break;
                    default:
                        throw new OneBotFailureException(RetCode::INTERNAL_HANDLER_ERROR, $action_obj, 'Unknown input validate type!');
                }
                if (!($valid = $func_name($action_obj->params[$k]))) {
                    break;
                }
            } elseif (is_array($v) && !Utils::isAssocArray($v)) {
                if (!in_array($action_obj->params[$k], $v)) {
                    $valid = false;
                    break;
                }
            }
        }
        if (!$valid) {
            throw new OneBotFailureException(RetCode::BAD_PARAM, $action_obj);
        }
    }

    /**
     * 验证 URL 是否为合法的 http(s) 地址，且解析后的 IP 不能是内网地址（防止 SSRF）
     *
     * 校验策略为 fail-closed：host 不合法、疑似 IP 混淆、或 DNS 无法解析出任何
     * 记录时，一律拒绝该 URL。
     *
     * @throws OneBotFailureException
     */
    public static function validateHttpUrl(string $url): void
    {
        $parse = parse_url($url);
        if (!isset($parse['scheme']) || !isset($parse['host']) || !is_string($parse['scheme']) || !is_string($parse['host'])
            || $parse['scheme'] !== 'http' && $parse['scheme'] !== 'https') {
            throw new OneBotFailureException(RetCode::NETWORK_ERROR);
        }
        // parse_url 解析出的 IPv6 host 会带有方括号，需要去掉
        $host = trim($parse['host'], '[]');
        // host 为空或包含非法字符（空格、% 等）时直接拒绝
        if (!self::isValidHostSyntax($host)) {
            throw new OneBotFailureException(RetCode::NETWORK_ERROR, null, 'URL host is invalid');
        }
        // 如果 host 本身是 IP 地址，则直接判断
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (self::isPrivateOrReservedIp($host)) {
                throw new OneBotFailureException(RetCode::NETWORK_ERROR, null, 'URL host resolves to a private or reserved IP address');
            }
            return;
        }
        // host 不是合法 IP 字面量，却只包含数字/点/0x 等字符，疑似十进制/八进制/十六进制/短横线 IP 混淆，直接拒绝
        if (self::isSuspiciousIpLiteral($host)) {
            throw new OneBotFailureException(RetCode::NETWORK_ERROR, null, 'URL host is not a valid hostname or IP address');
        }
        // 域名：解析 DNS（同时支持 A 记录的 ip 键与 AAAA 记录的 ipv6 键），解析失败或无记录时 fail-closed 拒绝
        $ips = static::lookupHostIps($host);
        if ($ips === []) {
            throw new OneBotFailureException(RetCode::NETWORK_ERROR, null, 'URL host cannot be resolved');
        }
        foreach ($ips as $ip) {
            if (self::isPrivateOrReservedIp($ip)) {
                throw new OneBotFailureException(RetCode::NETWORK_ERROR, null, 'URL host resolves to a private or reserved IP address');
            }
        }
    }

    /**
     * 根据 OneBot 12 标准的规则，来验证事件的参数是否合规
     * 不合规将抛出 OneBotException 并附带相应提示语
     * （虽然可能这里的代码很长，但是这样运行速度快一点）
     *
     * @param  array           $data 数据数组
     * @throws OneBotException
     */
    public static function validateEventParams(array $data)
    {
        // 每个 OneBot 事件必须有这几个参数
        if (!isset($data['type'], $data['id'], $data['detail_type'], $data['sub_type'])) {
            throw new OneBotException('onebot 12 requires type, id, detail_type, sub_type');
        }
        // 除元事件（type = meta）外，其他事件必须拥有 self 字段
        if ($data['type'] !== 'meta' && !isset($data['self'])) {
            throw new OneBotException('onebot 12 requires self');
        }
        // 如果拥有 self 时，self 字段必须包含 platform 和 user_id 字段
        if (isset($data['self']) && (!isset($data['self']['platform']) || !isset($data['self']['user_id']))) {
            throw new OneBotException('onebot 12 requires self.platform and self.user_id');
        }
        switch ($data['type']) {
            case 'message':
                if (!isset($data['message'])) {
                    throw new OneBotException('onebot 12 requires message');
                }
                // 验证 MessageSegment
                self::validateMessageSegment($data['message']);
                switch ($data['detail_type']) {
                    case 'group':
                        if (!isset($data['group_id'], $data['user_id'], $data['message_id'], $data['alt_message'])) {
                            throw new OneBotException('group message must have group_id, user_id, message, message_id, alt_message');
                        }
                        break;
                    case 'private':
                        if (!isset($data['user_id'], $data['message_id'], $data['alt_message'])) {
                            throw new OneBotException('private message must have user_id, message, message_id, alt_message');
                        }
                        break;
                    case 'channel':
                        if (!isset($data['channel_id'], $data['guild_id'], $data['user_id'], $data['message_id'])) {
                            throw new OneBotException('channel message must have channel_id, guild_id, user_id, message, message_id');
                        }
                        break;
                }
                break;
            case 'notice':
                switch ($data['detail_type']) {
                    case 'friend_increase':
                        if (!isset($data['user_id'])) {
                            throw new OneBotException('friend increase must have user_id');
                        }
                        break;
                    case 'friend_decrease':
                        if (!isset($data['user_id'])) {
                            throw new OneBotException('friend decrease must have user_id');
                        }
                        break;
                    case 'private_message_delete':
                        if (!isset($data['user_id'], $data['message_id'])) {
                            throw new OneBotException('private message delete must have user_id, message_id');
                        }
                        break;
                    case 'group_member_increase':
                        if (!isset($data['group_id'], $data['user_id'], $data['operator_id'])) {
                            throw new OneBotException('group member increase must have group_id, user_id, operator_id');
                        }
                        break;
                    case 'group_member_decrease':
                        if (!isset($data['group_id'], $data['user_id'], $data['operator_id'])) {
                            throw new OneBotException('group member decrease must have group_id, user_id, operator_id');
                        }
                        break;
                    case 'group_message_delete':
                        if (!isset($data['group_id'], $data['user_id'], $data['message_id'], $data['operator_id'])) {
                            throw new OneBotException('group message delete must have group_id, user_id, message_id');
                        }
                        break;
                    case 'guild_member_increase':
                        if (!isset($data['guild_id'], $data['user_id'], $data['operator_id'])) {
                            throw new OneBotException('guild member increase must have guild_id, user_id, operator_id');
                        }
                        break;
                    case 'guild_member_decrease':
                        if (!isset($data['guild_id'], $data['user_id'], $data['operator_id'])) {
                            throw new OneBotException('guild member decrease must have guild_id, user_id, operator_id');
                        }
                        break;
                    case 'channel_member_increase':
                        if (!isset($data['channel_id'], $data['guild_id'], $data['user_id'], $data['operator_id'])) {
                            throw new OneBotException('channel member increase must have channel_id, guild_id, user_id, operator_id');
                        }
                        break;
                    case 'channel_member_decrease':
                        if (!isset($data['channel_id'], $data['guild_id'], $data['user_id'], $data['operator_id'])) {
                            throw new OneBotException('channel member decrease must have channel_id, guild_id, user_id, operator_id');
                        }
                        break;
                    case 'channel_message_delete':
                        if (!isset($data['channel_id'], $data['guild_id'], $data['user_id'], $data['message_id'], $data['operator_id'])) {
                            throw new OneBotException('channel message delete must have channel_id, guild_id, user_id, message_id, operator_id');
                        }
                        break;
                    case 'channel_create':
                        if (!isset($data['channel_id'], $data['guild_id'], $data['operator_id'])) {
                            throw new OneBotException('channel create must have channel_id, guild_id, operator_id');
                        }
                        break;
                    case 'channel_delete':
                        if (!isset($data['channel_id'], $data['guild_id'], $data['operator_id'])) {
                            throw new OneBotException('channel delete must have channel_id, guild_id, operator_id');
                        }
                        break;
                }
                break;
            case 'request':
            case 'meta':
                break;
            default:
                throw new OneBotException('unknown event type');
        }
    }

    /**
     * 判断 IP 是否为内网或保留地址（IPv4/IPv6），用于防止 SSRF
     */
    public static function isPrivateOrReservedIp(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }
        if (strlen($packed) === 4) {
            $n = unpack('N', $packed)[1];
            // 0.0.0.0/8 保留地址
            if (($n & 0xFF000000) === 0x00000000) {
                return true;
            }
            // 10.0.0.0/8 私网地址
            if (($n & 0xFF000000) === 0x0A000000) {
                return true;
            }
            // 100.64.0.0/10 运营商级 NAT 地址
            if (($n & 0xFFC00000) === 0x64400000) {
                return true;
            }
            // 127.0.0.0/8 回环地址
            if (($n & 0xFF000000) === 0x7F000000) {
                return true;
            }
            // 169.254.0.0/16 链路本地地址
            if (($n & 0xFFFF0000) === 0xA9FE0000) {
                return true;
            }
            // 172.16.0.0/12 私网地址
            if (($n & 0xFFF00000) === 0xAC100000) {
                return true;
            }
            // 192.168.0.0/16 私网地址
            if (($n & 0xFFFF0000) === 0xC0A80000) {
                return true;
            }
            // 224.0.0.0/4 组播地址
            if (($n & 0xF0000000) === 0xE0000000) {
                return true;
            }
            // 240.0.0.0/4 保留地址
            if (($n & 0xF0000000) === 0xF0000000) {
                return true;
            }
            return false;
        }
        if (strlen($packed) === 16) {
            $bytes = unpack('C16', $packed);
            // ::1 回环地址
            if ($packed === str_repeat("\0", 15) . "\1") {
                return true;
            }
            // :: 未指定地址
            if ($packed === str_repeat("\0", 16)) {
                return true;
            }
            // fc00::/7 唯一本地地址
            if (($bytes[1] & 0xFE) === 0xFC) {
                return true;
            }
            // fe80::/10 链路本地地址
            if ($bytes[1] === 0xFE && ($bytes[2] & 0xC0) === 0x80) {
                return true;
            }
            // ::ffff:0:0/96 IPv4 映射地址（要求前 80 位全零，否则是合法全局地址），按 IPv4 规则判断
            if ($bytes[11] === 0xFF && $bytes[12] === 0xFF && strncmp($packed, str_repeat("\0", 10), 10) === 0) {
                return self::isPrivateOrReservedIp(sprintf('%d.%d.%d.%d', $bytes[13], $bytes[14], $bytes[15], $bytes[16]));
            }
            return false;
        }
        return false;
    }

    /**
     * 解析域名的 DNS 记录并提取全部 IP 列表（A 记录的 ip 键 + AAAA 记录的 ipv6 键）
     *
     * 查询失败或无任何有效记录时返回空数组，由调用方决定 fail-closed。
     */
    protected static function lookupHostIps(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            return [];
        }
        return static::extractIpsFromDnsRecords($records);
    }

    /**
     * 从 DNS 查询结果中提取 IP 列表
     *
     * 注意：dns_get_record 对 AAAA 记录返回的键是 ipv6 而非 ip，两者都需要处理，
     * 否则仅解析到内网 IPv6 的域名会绕过 SSRF 校验。
     */
    protected static function extractIpsFromDnsRecords(array $records): array
    {
        $ips = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            foreach (['ip', 'ipv6'] as $key) {
                if (isset($record[$key]) && is_string($record[$key]) && filter_var($record[$key], FILTER_VALIDATE_IP) !== false) {
                    $ips[] = $record[$key];
                }
            }
        }
        return array_values(array_unique($ips));
    }

    /**
     * 判断 host 是否满足基本的 URL 主机名语法（允许域名、IPv4、IPv6 字面量的合法字符）
     */
    private static function isValidHostSyntax(string $host): bool
    {
        return $host !== '' && preg_match('/^[0-9a-zA-Z:._-]+$/', $host) === 1;
    }

    /**
     * 判断 host 是否为疑似 IP 混淆形式（十进制/八进制/十六进制/短横线等）
     *
     * 这类 host 无法通过 FILTER_VALIDATE_IP，但某些网络库仍会将其当作 IP 使用，
     * 且它们不可能同时是合法的域名（域名必须包含字母），因此直接拒绝。
     */
    private static function isSuspiciousIpLiteral(string $host): bool
    {
        // 不含任何 ASCII 字母，只能是数字/点/冒号等（如 2130706433、127.1、0177.0.0.1）
        if (preg_match('/[a-zA-Z]/', $host) === 0) {
            return true;
        }
        // 0x 开头的十六进制形式（如 0x7f000001、0x7f.0.0.1）
        return preg_match('/^0[xX][0-9a-fA-F.]+$/', $host) === 1;
    }

    private static function validateExist(Action $action_obj, $k): bool
    {
        return isset($action_obj->params[$k]);
    }
}
