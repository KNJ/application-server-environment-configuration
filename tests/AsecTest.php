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
            [
                'deep' => [
                    'key' => 'here',
                    'and' => [
                        'mazed' => [
                            'key' => 'there',
                        ]
                    ]
                ],
            ],
            Asec::take('deep.and')
        );
        $this->assertArraySubset(
            [
                [
                    'id' => 1,
                    'name' => 'Alice',
                ],
                [
                    'id' => 2,
                    'name' => 'Bob',
                ],
                [
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
     * @depends testGetArray
     * @depends testTakeMultipleValues
     */
    public function testAssign()
    {
        $mass = [
            'app' => [
                'name' => 'Asec',
                'environments' => [
                    'production',
                    'development',
                    'test',
                ],
            ]
        ];

        $this->assertArraySubset($mass, Asec::assign($mass));
        $this->assertSame($mass['app']['name'], Asec::get('app.name'));
        $this->assertArraySubset($mass['app']['environments'], Asec::get('app.environments'));
        $this->assertEquals($mass['app'], Asec::take('app'));
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
            [
                'key' => 'here',
                'and' => [
                    'mazed' => [
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

    /**
     * @depends testSetString
     * @depends testDelete
     */
    public function testMultipleActions()
    {
        // set -> delete
        $selector = 'this.message.will.be.deleted.soon';
        Asec::set($selector, 'remain');
        Asec::delete($selector);
        $this->assertNull(Asec::get($selector));

        // assign => delete
        $mass = [
            'I' => [
                'am' => [
                    'a' => 'hero',
                    'an' => 'evil',
                ]
            ]
        ];
        Asec::assign($mass);
        Asec::delete('I.am.a');
        $this->assertNull(Asec::get('I.am.a'));

        // assign => set
        Asec::set('I.was.a', 'champion');
        $this->assertSame('champion', Asec::get('I.was.a'));

        // assign => take
        $this->assertArraySubset(
            [
                'am' => [
                    'an' => 'evil',
                ],
                'was' => [
                    'a' => 'champion',
                ]
            ],
            Asec::take('I')
        );
    }
}
