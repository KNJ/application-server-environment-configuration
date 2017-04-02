<?php

namespace Wazly;

class AsecTest extends \PHPUnit\Framework\TestCase
{
    public function setUp()
    {
        Asec::clearInstance();
        Asec::configure([
            'filename' => '.asec.test.json',
        ]);
    }

    public function testCannotFindFile()
    {
        $filename = '.asec.not.found.json';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($filename);
        Asec::configure([
            'filename' => $filename,
        ]);
        Asec::getInstance();
    }

    public function testInvalidJson()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid');
        Asec::configure([
            'filename' => '.asec.invalid.json',
        ]);
        Asec::getInstance();
    }

    public function testGetRoot()
    {
        $this->assertSame(realpath(__DIR__ . '/../'), Asec::getRoot());
    }

    public function testGetString()
    {
        $this->assertSame('asec_test', Asec::get('name'));
    }

    public function testGetInt()
    {
        $this->assertSame(18, Asec::get('number'));
    }

    public function testGetBool()
    {
        $this->assertTrue(Asec::get('public'));
        $this->assertFalse(Asec::get('private'));
    }

    public function testGetArray()
    {
        $this->assertArraySubset(['foo', 'bar', 'baz'], Asec::get('items'));
    }

    public function testGetNested()
    {
        $this->assertSame('here', Asec::get('deep.and.deep.key'));
        $this->assertSame('there', Asec::get('deep.and.deep.and.mazed.key'));
    }

    public function testGetDefault()
    {
        $this->assertNull(Asec::get('fake.key'));
        $this->assertSame('fake', Asec::get('fake.key', 'fake'));
    }

    public function testTakeSingleValue()
    {
        $this->assertSame('asec_test', Asec::take('name'));
        $this->assertSame(18, Asec::take('number'));
        $this->assertTrue(Asec::take('public'));
        $this->assertFalse(Asec::take('private'));
        $this->assertArraySubset(['foo', 'bar', 'baz'], Asec::take('items'));
        $this->assertNull(Asec::take('fake.key'));
        $this->assertSame('fake', Asec::take('fake.key', 'fake'));
    }

    public function testTakeMultipleValues()
    {
        $this->assertEquals(
            (object)[
                'deep' => (object)[
                    'key' => 'here',
                    'and' => (object)[
                        'mazed' => (object)[
                            'key' => 'there',
                        ]
                    ]
                ],
            ],
            Asec::take('deep.and')
        );
        $this->assertArraySubset(
            [
                (object)[
                    'id' => 1,
                    'name' => 'Alice',
                ],
                (object)[
                    'id' => 2,
                    'name' => 'Bob',
                ],
                (object)[
                    'id' => 3,
                    'name' => 'Eve',
                ]
            ],
            Asec::take('object.and.list')
        );
    }

    /**
     * @depends testGetString
     */
    public function testSetString()
    {
        $str = '文字列';
        $this->assertSame($str, Asec::set('set.string', $str));
        $this->assertSame($str, Asec::get('set.string'));
    }

    /**
     * @depends testGetInt
     */
    public function testSetInt()
    {
        $num = 2017;
        $this->assertSame($num, Asec::set('set.int', $num));
        $this->assertSame($num, Asec::get('set.int'));
    }

    /**
     * @depends testGetArray
     */
    public function testSetArray()
    {
        $list = ['一', '二', '三', '四'];
        $this->assertArraySubset($list, Asec::set('set.list', $list));
        $this->assertSame($list, Asec::get('set.list'));

        $kv = [
            'name' => 'KNJ',
            'status' => 0,
            'awards' => [],
            'languages' => ['Japanese', 'English'],
            'accounts' => [
                'github' => 'KNJ',
                'twitter' => 'Kanjasan',
                'facebook' => null,
            ]
        ];
        $this->assertArraySubset($kv, Asec::set('set.kv', $kv));
        $this->assertSame($kv, Asec::get('set.kv'));
    }

    /**
     * @depends testGetString
     * @depends testGetDefault
     */
    public function testDelete()
    {
        // Soft delete
        $this->assertSame('asec_test', Asec::delete('name'));
        $this->assertNull(Asec::get('name'));
        $this->assertEquals(
            (object)[
                'key' => 'here',
                'and' => (object)[
                    'mazed' => (object)[
                        'key' => 'there',
                    ]
                ]
            ],
            Asec::delete('deep.and.deep')
        );

        // Hard delete and rebuild
        $this->assertNull(Asec::take('deep.and.deep'));
        $this->assertNull(Asec::get('name'));
    }
}
