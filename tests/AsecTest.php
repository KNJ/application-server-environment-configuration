<?php

namespace Wazly;

class ASECTest extends \PHPUnit\Framework\TestCase
{
    public function setUp()
    {
        ASEC::clearInstance();
        ASEC::configure([
            'filename' => '.asec.test.json',
        ]);
    }

    public function testCannotFindFile()
    {
        $filename = '.asec.not.found.json';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($filename);
        ASEC::configure([
            'filename' => $filename,
        ]);
        ASEC::getInstance();
    }

    public function testInvalidJson()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid');
        ASEC::configure([
            'filename' => '.asec.invalid.json',
        ]);
        ASEC::getInstance();
    }

    public function testGetRoot()
    {
        $this->assertSame(realpath(__DIR__ . '/../'), ASEC::getRoot());
    }

    public function testGetString()
    {
        $this->assertSame('asec_test', ASEC::get('name'));
    }

    public function testGetInt()
    {
        $this->assertSame(18, ASEC::get('number'));
    }

    public function testGetBool()
    {
        $this->assertTrue(ASEC::get('public'));
        $this->assertFalse(ASEC::get('private'));
    }

    public function testGetArray()
    {
        $this->assertArraySubset(['foo', 'bar', 'baz'], ASEC::get('items'));
    }

    public function testGetNested()
    {
        $this->assertSame('here', ASEC::get('deep.and.deep.key'));
        $this->assertSame('there', ASEC::get('deep.and.deep.and.mazed.key'));
    }

    public function testGetDefault()
    {
        $this->assertNull(ASEC::get('fake.key'));
        $this->assertSame('fake', ASEC::get('fake.key', 'fake'));
    }

    public function testTakeSingleValue()
    {
        $this->assertSame('asec_test', ASEC::take('name'));
        $this->assertSame(18, ASEC::take('number'));
        $this->assertTrue(ASEC::take('public'));
        $this->assertFalse(ASEC::take('private'));
        $this->assertArraySubset(['foo', 'bar', 'baz'], ASEC::take('items'));
        $this->assertNull(ASEC::take('fake.key'));
        $this->assertSame('fake', ASEC::take('fake.key', 'fake'));
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
            ASEC::take('deep.and')
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
            ASEC::take('object.and.list')
        );
    }

    /**
     * @depends testGetString
     */
    public function testSetString()
    {
        $str = '文字列';
        $this->assertSame($str, ASEC::set('set.string', $str));
        $this->assertSame($str, ASEC::get('set.string'));
    }

    /**
     * @depends testGetInt
     */
    public function testSetInt()
    {
        $num = 2017;
        $this->assertSame($num, ASEC::set('set.int', $num));
        $this->assertSame($num, ASEC::get('set.int'));
    }

    /**
     * @depends testGetArray
     */
    public function testSetArray()
    {
        $list = ['一', '二', '三', '四'];
        $this->assertArraySubset($list, ASEC::set('set.list', $list));
        $this->assertSame($list, ASEC::get('set.list'));

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
        $this->assertArraySubset($kv, ASEC::set('set.kv', $kv));
        $this->assertSame($kv, ASEC::get('set.kv'));
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
                'name' => 'ASEC',
                'environments' => [
                    'production',
                    'development',
                    'test',
                ],
            ]
        ];

        $this->assertArraySubset($mass, ASEC::assign($mass));
        $this->assertSame($mass['app']['name'], ASEC::get('app.name'));
        $this->assertArraySubset($mass['app']['environments'], ASEC::get('app.environments'));
        $this->assertEquals($mass['app'], ASEC::take('app'));
    }

    /**
     * @depends testGetString
     * @depends testGetDefault
     */
    public function testDelete()
    {
        // Soft delete
        $this->assertSame('asec_test', ASEC::delete('name'));
        $this->assertNull(ASEC::get('name'));
        $this->assertEquals(
            [
                'key' => 'here',
                'and' => [
                    'mazed' => [
                        'key' => 'there',
                    ]
                ]
            ],
            ASEC::delete('deep.and.deep')
        );

        // Hard delete and rebuild
        $this->assertNull(ASEC::take('deep.and.deep'));
        $this->assertNull(ASEC::get('name'));
    }

    /**
     * @depends testSetString
     * @depends testDelete
     */
    public function testMultipleActions()
    {
        // set -> delete
        $selector = 'this.message.will.be.deleted.soon';
        ASEC::set($selector, 'remain');
        ASEC::delete($selector);
        $this->assertNull(ASEC::get($selector));

        // assign => delete
        $mass = [
            'I' => [
                'am' => [
                    'a' => 'hero',
                    'an' => 'evil',
                ]
            ]
        ];
        ASEC::assign($mass);
        ASEC::delete('I.am.a');
        $this->assertNull(ASEC::get('I.am.a'));

        // assign => set
        ASEC::set('I.was.a', 'champion');
        $this->assertSame('champion', ASEC::get('I.was.a'));

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
            ASEC::take('I')
        );
    }
}
