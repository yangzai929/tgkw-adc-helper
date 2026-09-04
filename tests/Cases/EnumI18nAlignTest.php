<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace HyperfTest\Cases;

use TgkwAdc\Annotation\EnumI18n;
use TgkwAdc\Annotation\EnumI18nGroup;
use TgkwAdc\Annotation\EnumI18nInterface;
use TgkwAdc\Helper\EnumStore;
use TgkwAdc\Resource\TableListColumnsResource;
use TgkwAdc\Trait\EnumI18nGet;

#[EnumI18nGroup(groupCode: 'table_columns', info: '表格列测试')]
enum TableColumnI18n: string implements EnumI18nInterface
{
    use EnumI18nGet;

    #[EnumI18n(txt: '名称', i18nTxt: ['en' => 'Name'], width: '160px', align: 'center')]
    case NAME = 'name';

    #[EnumI18n(txt: '备注')]
    case REMARK = 'remark';
}

/**
 * @internal
 * @covers \TgkwAdc\Annotation\EnumI18n
 * @covers \TgkwAdc\Resource\TableListColumnsResource
 * @covers \TgkwAdc\Trait\EnumI18nGet
 */
class EnumI18nAlignTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        EnumStore::clear(TableColumnI18n::class);
    }

    protected function tearDown(): void
    {
        EnumStore::clear(TableColumnI18n::class);
        parent::tearDown();
    }

    public function testAlignIsExposedByEnumMetadata(): void
    {
        $enums = TableColumnI18n::getEnums(true);

        $this->assertSame('center', $enums['NAME']['align']);
        $this->assertNull($enums['REMARK']['align']);
    }

    public function testAlignIsIncludedInTableColumnsAndResource(): void
    {
        $result = buildTableColumnsWithValueMaps(TableColumnI18n::class);

        $this->assertSame('center', $result['columns'][0]['align']);
        $this->assertArrayNotHasKey('align', $result['columns'][1]);

        $resource = new TableListColumnsResource($result);
        $formatted = $resource->toArray();

        $this->assertSame('center', $formatted['columns'][0]['align']);
        $this->assertSame('center', $formatted['columns'][1]['align']);
    }

    public function testResourceUsesDefaultWidthAndAlignWhenTheyAreNotSet(): void
    {
        $resource = new TableListColumnsResource([
            'columns' => [
                ['key' => 'name'],
            ],
        ]);

        $formatted = $resource->toArray();

        $this->assertSame('150px', $formatted['columns'][0]['width']);
        $this->assertSame('center', $formatted['columns'][0]['align']);
    }
}
