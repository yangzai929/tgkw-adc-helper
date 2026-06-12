<?php

namespace TgkwAdc\Utils;

use Hyperf\Context\ApplicationContext;
use Hyperf\Redis\RedisFactory;

/**
 * Redis 工具类，自动统一前缀，封装常用数据类型操作。
 */
class RedisUtils
{
    // 前缀缓存（运行期不变）
    private static ?string $prefix = null;

    // 全局统一前缀，确保以分隔符结尾
    protected static function prefix(): string
    {
        if (self::$prefix === null) {
            $name = env('APP_NAME') ?: 'app';
            self::$prefix = rtrim($name, ':_') . ':';
        }

        return self::$prefix;
    }

    protected static function getRedis(?string $pool = 'default')
    {
        /** @var RedisFactory $factory */
        $factory = ApplicationContext::getContainer()->get(RedisFactory::class);

        return $factory->get($pool ?? 'default');
    }

    // 自动拼接前缀
    protected static function buildKey(string $key): string
    {
        return self::prefix() . $key;
    }

    // 批量拼接前缀
    protected static function buildKeys(array $keys): array
    {
        return array_map(fn ($k) => self::buildKey($k), $keys);
    }

    /* ===================== 通用 Key 操作 ===================== */

    public static function del(string ...$keys)
    {
        return self::getRedis()->del(...self::buildKeys($keys));
    }

    public static function unlink(string ...$keys)
    {
        return self::getRedis()->unlink(...self::buildKeys($keys));
    }

    public static function exists(string ...$keys)
    {
        return self::getRedis()->exists(...self::buildKeys($keys));
    }

    public static function expire(string $key, int $ttl, ?string $mode = null)
    {
        return $mode === null
            ? self::getRedis()->expire(self::buildKey($key), $ttl)
            : self::getRedis()->expire(self::buildKey($key), $ttl, $mode);
    }

    public static function expireAt(string $key, int $timestamp)
    {
        return self::getRedis()->expireAt(self::buildKey($key), $timestamp);
    }

    public static function pExpire(string $key, int $ttlMs)
    {
        return self::getRedis()->pExpire(self::buildKey($key), $ttlMs);
    }

    public static function pExpireAt(string $key, int $timestampMs)
    {
        return self::getRedis()->pExpireAt(self::buildKey($key), $timestampMs);
    }

    public static function expireTime(string $key)
    {
        return self::getRedis()->expiretime(self::buildKey($key));
    }

    public static function pExpireTime(string $key)
    {
        return self::getRedis()->pexpiretime(self::buildKey($key));
    }

    public static function persist(string $key)
    {
        return self::getRedis()->persist(self::buildKey($key));
    }

    public static function ttl(string $key)
    {
        return self::getRedis()->ttl(self::buildKey($key));
    }

    public static function pTtl(string $key)
    {
        return self::getRedis()->pttl(self::buildKey($key));
    }

    public static function type(string $key)
    {
        return self::getRedis()->type(self::buildKey($key));
    }

    public static function rename(string $src, string $dst)
    {
        return self::getRedis()->rename(self::buildKey($src), self::buildKey($dst));
    }

    public static function renameNx(string $src, string $dst)
    {
        return self::getRedis()->renameNx(self::buildKey($src), self::buildKey($dst));
    }

    public static function touch(string ...$keys)
    {
        return self::getRedis()->touch(self::buildKeys($keys));
    }

    public static function copy(string $src, string $dst, ?array $options = null)
    {
        return self::getRedis()->copy(self::buildKey($src), self::buildKey($dst), $options);
    }

    public static function move(string $key, int $db)
    {
        return self::getRedis()->move(self::buildKey($key), $db);
    }

    public static function dump(string $key)
    {
        return self::getRedis()->dump(self::buildKey($key));
    }

    public static function restore(string $key, int $ttl, string $value, ?array $options = null)
    {
        return self::getRedis()->restore(self::buildKey($key), $ttl, $value, $options);
    }

    public static function randomKey()
    {
        return self::getRedis()->randomKey();
    }

    public static function keys(string $pattern)
    {
        return self::getRedis()->keys(self::buildKey($pattern));
    }

    public static function scan(?int &$iterator, ?string $pattern = null, int $count = 0)
    {
        return self::getRedis()->scan($iterator, $pattern === null ? null : self::buildKey($pattern), $count);
    }

    /* ===================== String 字符串 ===================== */

    public static function set(string $key, $value, $options = null)
    {
        return self::getRedis()->set(self::buildKey($key), $value, $options);
    }

    public static function setEx(string $key, int $ttl, $value)
    {
        return self::getRedis()->setEx(self::buildKey($key), $ttl, $value);
    }

    public static function pSetEx(string $key, int $ttlMs, $value)
    {
        return self::getRedis()->psetex(self::buildKey($key), $ttlMs, $value);
    }

    public static function setNx(string $key, $value)
    {
        return self::getRedis()->setnx(self::buildKey($key), $value);
    }

    public static function get(string $key)
    {
        return self::getRedis()->get(self::buildKey($key));
    }

    public static function getSet(string $key, $value)
    {
        return self::getRedis()->getset(self::buildKey($key), $value);
    }

    public static function getDel(string $key)
    {
        return self::getRedis()->getDel(self::buildKey($key));
    }

    public static function getEx(string $key, array $options = [])
    {
        return self::getRedis()->getEx(self::buildKey($key), $options);
    }

    public static function mSet(array $keyValues)
    {
        $prefixed = [];
        foreach ($keyValues as $k => $v) {
            $prefixed[self::buildKey($k)] = $v;
        }

        return self::getRedis()->mset($prefixed);
    }

    public static function mGet(array $keys)
    {
        return self::getRedis()->mget(self::buildKeys($keys));
    }

    public static function incr(string $key, int $by = 1)
    {
        return self::getRedis()->incr(self::buildKey($key), $by);
    }

    public static function incrBy(string $key, int $value)
    {
        return self::getRedis()->incrBy(self::buildKey($key), $value);
    }

    public static function incrByFloat(string $key, float $value)
    {
        return self::getRedis()->incrByFloat(self::buildKey($key), $value);
    }

    public static function decr(string $key, int $by = 1)
    {
        return self::getRedis()->decr(self::buildKey($key), $by);
    }

    public static function decrBy(string $key, int $value)
    {
        return self::getRedis()->decrBy(self::buildKey($key), $value);
    }

    public static function append(string $key, $value)
    {
        return self::getRedis()->append(self::buildKey($key), $value);
    }

    public static function strLen(string $key)
    {
        return self::getRedis()->strlen(self::buildKey($key));
    }

    public static function getRange(string $key, int $start, int $end)
    {
        return self::getRedis()->getRange(self::buildKey($key), $start, $end);
    }

    public static function setRange(string $key, int $offset, string $value)
    {
        return self::getRedis()->setRange(self::buildKey($key), $offset, $value);
    }

    /* ===================== Hash 哈希 ===================== */

    public static function hSet(string $key, string $field, $value)
    {
        return self::getRedis()->hSet(self::buildKey($key), $field, $value);
    }

    public static function hSetNx(string $key, string $field, $value)
    {
        return self::getRedis()->hSetNx(self::buildKey($key), $field, $value);
    }

    public static function hGet(string $key, string $field)
    {
        return self::getRedis()->hGet(self::buildKey($key), $field);
    }

    public static function hMSet(string $key, array $fieldVals)
    {
        return self::getRedis()->hMSet(self::buildKey($key), $fieldVals);
    }

    public static function hMGet(string $key, array $fields)
    {
        return self::getRedis()->hMGet(self::buildKey($key), $fields);
    }

    public static function hGetAll(string $key)
    {
        return self::getRedis()->hGetAll(self::buildKey($key));
    }

    public static function hDel(string $key, string ...$fields)
    {
        return self::getRedis()->hDel(self::buildKey($key), ...$fields);
    }

    public static function hExists(string $key, string $field)
    {
        return self::getRedis()->hExists(self::buildKey($key), $field);
    }

    public static function hKeys(string $key)
    {
        return self::getRedis()->hKeys(self::buildKey($key));
    }

    public static function hVals(string $key)
    {
        return self::getRedis()->hVals(self::buildKey($key));
    }

    public static function hLen(string $key)
    {
        return self::getRedis()->hLen(self::buildKey($key));
    }

    public static function hIncrBy(string $key, string $field, int $value)
    {
        return self::getRedis()->hIncrBy(self::buildKey($key), $field, $value);
    }

    public static function hIncrByFloat(string $key, string $field, float $value)
    {
        return self::getRedis()->hIncrByFloat(self::buildKey($key), $field, $value);
    }

    public static function hStrLen(string $key, string $field)
    {
        return self::getRedis()->hStrLen(self::buildKey($key), $field);
    }

    public static function hRandField(string $key, ?array $options = null)
    {
        return self::getRedis()->hRandField(self::buildKey($key), $options);
    }

    public static function hScan(string $key, ?int &$iterator, ?string $pattern = null, int $count = 0)
    {
        return self::getRedis()->hscan(self::buildKey($key), $iterator, $pattern, $count);
    }

    /* ===================== List 列表 ===================== */

    public static function lPush(string $key, ...$values)
    {
        return self::getRedis()->lPush(self::buildKey($key), ...$values);
    }

    public static function rPush(string $key, ...$values)
    {
        return self::getRedis()->rPush(self::buildKey($key), ...$values);
    }

    public static function lPushX(string $key, $value)
    {
        return self::getRedis()->lPushx(self::buildKey($key), $value);
    }

    public static function rPushX(string $key, $value)
    {
        return self::getRedis()->rPushx(self::buildKey($key), $value);
    }

    public static function lPop(string $key, int $count = 0)
    {
        return self::getRedis()->lPop(self::buildKey($key), $count);
    }

    public static function rPop(string $key, int $count = 0)
    {
        return self::getRedis()->rPop(self::buildKey($key), $count);
    }

    public static function lLen(string $key)
    {
        return self::getRedis()->lLen(self::buildKey($key));
    }

    public static function lRange(string $key, int $start, int $end)
    {
        return self::getRedis()->lrange(self::buildKey($key), $start, $end);
    }

    public static function lIndex(string $key, int $index)
    {
        return self::getRedis()->lindex(self::buildKey($key), $index);
    }

    public static function lSet(string $key, int $index, $value)
    {
        return self::getRedis()->lSet(self::buildKey($key), $index, $value);
    }

    public static function lRem(string $key, $value, int $count = 0)
    {
        return self::getRedis()->lrem(self::buildKey($key), $value, $count);
    }

    public static function lTrim(string $key, int $start, int $end)
    {
        return self::getRedis()->ltrim(self::buildKey($key), $start, $end);
    }

    public static function lInsert(string $key, string $pos, $pivot, $value)
    {
        return self::getRedis()->lInsert(self::buildKey($key), $pos, $pivot, $value);
    }

    public static function lPos(string $key, $value, ?array $options = null)
    {
        return self::getRedis()->lPos(self::buildKey($key), $value, $options);
    }

    public static function rPopLPush(string $src, string $dst)
    {
        return self::getRedis()->rpoplpush(self::buildKey($src), self::buildKey($dst));
    }

    public static function lMove(string $src, string $dst, string $whereFrom, string $whereTo)
    {
        return self::getRedis()->lMove(self::buildKey($src), self::buildKey($dst), $whereFrom, $whereTo);
    }

    /* ===================== Set 集合 ===================== */

    public static function sAdd(string $key, $value, ...$other)
    {
        return self::getRedis()->sAdd(self::buildKey($key), $value, ...$other);
    }

    public static function sRem(string $key, $value, ...$other)
    {
        return self::getRedis()->srem(self::buildKey($key), $value, ...$other);
    }

    public static function sMembers(string $key)
    {
        return self::getRedis()->sMembers(self::buildKey($key));
    }

    public static function sIsMember(string $key, $value)
    {
        return self::getRedis()->sismember(self::buildKey($key), $value);
    }

    public static function sCard(string $key)
    {
        return self::getRedis()->scard(self::buildKey($key));
    }

    public static function sPop(string $key, int $count = 0)
    {
        return self::getRedis()->sPop(self::buildKey($key), $count);
    }

    public static function sRandMember(string $key, int $count = 0)
    {
        return self::getRedis()->sRandMember(self::buildKey($key), $count);
    }

    public static function sInter(string $key, string ...$otherKeys)
    {
        return self::getRedis()->sInter(self::buildKey($key), ...self::buildKeys($otherKeys));
    }

    public static function sUnion(string $key, string ...$otherKeys)
    {
        return self::getRedis()->sUnion(self::buildKey($key), ...self::buildKeys($otherKeys));
    }

    public static function sDiff(string $key, string ...$otherKeys)
    {
        return self::getRedis()->sDiff(self::buildKey($key), ...self::buildKeys($otherKeys));
    }

    public static function sMove(string $src, string $dst, $value)
    {
        return self::getRedis()->sMove(self::buildKey($src), self::buildKey($dst), $value);
    }

    public static function sMisMember(string $key, string $member, string ...$otherMembers)
    {
        return self::getRedis()->sMisMember(self::buildKey($key), $member, ...$otherMembers);
    }

    public static function sInterStore(string $dst, string $key, string ...$otherKeys)
    {
        return self::getRedis()->sInterStore(self::buildKey($dst), self::buildKey($key), ...self::buildKeys($otherKeys));
    }

    public static function sUnionStore(string $dst, string $key, string ...$otherKeys)
    {
        return self::getRedis()->sUnionStore(self::buildKey($dst), self::buildKey($key), ...self::buildKeys($otherKeys));
    }

    public static function sDiffStore(string $dst, string $key, string ...$otherKeys)
    {
        return self::getRedis()->sDiffStore(self::buildKey($dst), self::buildKey($key), ...self::buildKeys($otherKeys));
    }

    public static function sScan(string $key, ?int &$iterator, ?string $pattern = null, int $count = 0)
    {
        return self::getRedis()->sscan(self::buildKey($key), $iterator, $pattern, $count);
    }

    /* ===================== Sorted Set 有序集合 ===================== */

    public static function zAdd(string $key, $scoreOrOptions, ...$moreScoresAndMems)
    {
        return self::getRedis()->zAdd(self::buildKey($key), $scoreOrOptions, ...$moreScoresAndMems);
    }

    public static function zRem(string $key, $member, ...$otherMembers)
    {
        return self::getRedis()->zRem(self::buildKey($key), $member, ...$otherMembers);
    }

    public static function zScore(string $key, $member)
    {
        return self::getRedis()->zScore(self::buildKey($key), $member);
    }

    public static function zIncrBy(string $key, float $value, $member)
    {
        return self::getRedis()->zIncrBy(self::buildKey($key), $value, $member);
    }

    public static function zCard(string $key)
    {
        return self::getRedis()->zCard(self::buildKey($key));
    }

    public static function zCount(string $key, $min, $max)
    {
        return self::getRedis()->zCount(self::buildKey($key), $min, $max);
    }

    public static function zRank(string $key, $member)
    {
        return self::getRedis()->zRank(self::buildKey($key), $member);
    }

    public static function zRevRank(string $key, $member)
    {
        return self::getRedis()->zRevRank(self::buildKey($key), $member);
    }

    public static function zRange(string $key, int $start, int $end, $options = null)
    {
        return self::getRedis()->zRange(self::buildKey($key), $start, $end, $options);
    }

    public static function zRevRange(string $key, int $start, int $end, $scores = null)
    {
        return self::getRedis()->zRevRange(self::buildKey($key), $start, $end, $scores);
    }

    public static function zRangeByScore(string $key, $start, $end, array $options = [])
    {
        return self::getRedis()->zRangeByScore(self::buildKey($key), $start, $end, $options);
    }

    public static function zRevRangeByScore(string $key, $max, $min, $options = [])
    {
        return self::getRedis()->zRevRangeByScore(self::buildKey($key), $max, $min, $options);
    }

    public static function zRemRangeByScore(string $key, $min, $max)
    {
        return self::getRedis()->zRemRangeByScore(self::buildKey($key), $min, $max);
    }

    public static function zRemRangeByRank(string $key, int $start, int $end)
    {
        return self::getRedis()->zRemRangeByRank(self::buildKey($key), $start, $end);
    }

    public static function zPopMin(string $key, ?int $count = null)
    {
        return self::getRedis()->zPopMin(self::buildKey($key), $count);
    }

    public static function zPopMax(string $key, ?int $count = null)
    {
        return self::getRedis()->zPopMax(self::buildKey($key), $count);
    }

    public static function zMscore(string $key, $member, ...$otherMembers)
    {
        return self::getRedis()->zMscore(self::buildKey($key), $member, ...$otherMembers);
    }

    public static function zRandMember(string $key, ?array $options = null)
    {
        return self::getRedis()->zRandMember(self::buildKey($key), $options);
    }

    public static function zLexCount(string $key, string $min, string $max)
    {
        return self::getRedis()->zLexCount(self::buildKey($key), $min, $max);
    }

    public static function zRangeByLex(string $key, string $min, string $max, int $offset = -1, int $count = -1)
    {
        return self::getRedis()->zRangeByLex(self::buildKey($key), $min, $max, $offset, $count);
    }

    public static function zRevRangeByLex(string $key, string $max, string $min, int $offset = -1, int $count = -1)
    {
        return self::getRedis()->zRevRangeByLex(self::buildKey($key), $max, $min, $offset, $count);
    }

    public static function zRemRangeByLex(string $key, string $min, string $max)
    {
        return self::getRedis()->zRemRangeByLex(self::buildKey($key), $min, $max);
    }

    public static function zInterStore(string $dst, array $keys, ?array $weights = null, ?string $aggregate = null)
    {
        return self::getRedis()->zinterstore(self::buildKey($dst), self::buildKeys($keys), $weights, $aggregate);
    }

    public static function zUnionStore(string $dst, array $keys, ?array $weights = null, ?string $aggregate = null)
    {
        return self::getRedis()->zunionstore(self::buildKey($dst), self::buildKeys($keys), $weights, $aggregate);
    }

    public static function zScan(string $key, ?int &$iterator, ?string $pattern = null, int $count = 0)
    {
        return self::getRedis()->zscan(self::buildKey($key), $iterator, $pattern, $count);
    }

    /* ===================== HyperLogLog ===================== */

    public static function pfAdd(string $key, array $elements)
    {
        return self::getRedis()->pfadd(self::buildKey($key), $elements);
    }

    public static function pfCount($keyOrKeys)
    {
        $keys = is_array($keyOrKeys) ? self::buildKeys($keyOrKeys) : self::buildKey($keyOrKeys);

        return self::getRedis()->pfcount($keys);
    }

    public static function pfMerge(string $dst, array $srcKeys)
    {
        return self::getRedis()->pfmerge(self::buildKey($dst), self::buildKeys($srcKeys));
    }

    /* ===================== Bitmap 位图 ===================== */

    public static function setBit(string $key, int $offset, bool $value)
    {
        return self::getRedis()->setBit(self::buildKey($key), $offset, $value);
    }

    public static function getBit(string $key, int $offset)
    {
        return self::getRedis()->getBit(self::buildKey($key), $offset);
    }

    public static function bitCount(string $key, int $start = 0, int $end = -1, bool $byBit = false)
    {
        return self::getRedis()->bitcount(self::buildKey($key), $start, $end, $byBit);
    }

    public static function bitPos(string $key, bool $bit, int $start = 0, int $end = -1, bool $byBit = false)
    {
        return self::getRedis()->bitpos(self::buildKey($key), $bit, $start, $end, $byBit);
    }

    /* ===================== Geo 地理位置 ===================== */

    public static function geoAdd(string $key, float $lng, float $lat, string $member, ...$other)
    {
        return self::getRedis()->geoadd(self::buildKey($key), $lng, $lat, $member, ...$other);
    }

    public static function geoPos(string $key, string $member, string ...$other)
    {
        return self::getRedis()->geopos(self::buildKey($key), $member, ...$other);
    }

    public static function geoDist(string $key, string $src, string $dst, ?string $unit = null)
    {
        return self::getRedis()->geodist(self::buildKey($key), $src, $dst, $unit);
    }

    public static function geoHash(string $key, string $member, string ...$other)
    {
        return self::getRedis()->geohash(self::buildKey($key), $member, ...$other);
    }

    public static function geoSearch(string $key, $position, $shape, string $unit, array $options = [])
    {
        return self::getRedis()->geosearch(self::buildKey($key), $position, $shape, $unit, $options);
    }

    public static function geoSearchStore(string $dst, string $src, $position, $shape, string $unit, array $options = [])
    {
        return self::getRedis()->geosearchstore(self::buildKey($dst), self::buildKey($src), $position, $shape, $unit, $options);
    }
}
