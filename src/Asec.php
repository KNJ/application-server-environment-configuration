<?php

namespace Wazly;

use Wazly\ASEC\{Buffer, Singleton};
use BadMethodCallException;
use RuntimeException;

final class ASEC
{
    use Singleton;

    public static $master;
    public static $temporary = [];
    private static $filename = '.asec.json';
    private static $root;
    private static $permanent = false;
    private $buffer;

    public function boot()
    {
        $path = __DIR__;

        // Try to find .asec.json
        // .asec.jsonを探す
        while ($path !== '') {
            if (file_exists($path . '/' . self::$filename)) {
                self::$root = $path;
                self::$master = json_decode(
                    file_get_contents($path . '/' . self::$filename),
                    true
                );
                if (is_null(self::$master)) {
                    throw new RuntimeException('Invalid JSON format.');
                }
                $this->rebuild(new Buffer);
                break;
            }
            $i = mb_strrpos($path, '/');
            $path = mb_substr($path, 0, $i);
        }

        if (!isset(self::$master)) {
            throw new RuntimeException('Cannot find "' . self::$filename . '" file.');
        }
    }

    /**
     * configure() sets your configuration.
     * - filename:  ASEC loads this file (default: .asec.json)
     * - permanent: Whether to write changes to the file (default: false)
     *
     * 環境設定を行う
     * - filename:  変数定義ファイル名 (default: .asec.json)
     * - permanent: キーや値の変更をファイルに書き込むかどうか (default: false)
     *
     * @param  array $conf            Definition of your configuration
     * @throws BadMethodCallException Must be called before boot()
     * @return void
     */
    public static function configure(array $conf = [])
    {
        if (self::hasInstance() === true) {
            throw new BadMethodCallException('ASEC::configure() must be called before the instance is created.');
        }

        if (isset($conf['filename'])) {
            self::$filename = $conf['filename'];
        }

        if (isset($conf['permanent'])) {
            self::$permanent = $conf['permanent'];
        }
    }

    /**
     * getRoot() returns the directory path where .asec.json is located.
     *
     * .asec.jsonが置かれているディレクトリのパスを返す
     *
     * @return string Directory where .asec.json is located
     */
    public static function getRoot(): string
    {
        self::getInstance();
        return self::$root;
    }

    /**
     * get() returns the value of the end key.
     *
     * 末端キーに対応する値を返す
     *
     * @param  string $selector Position of an end key
     * @param  mixed  $default  Default value (in case the key is not found)
     * @return mixed
     */
    public static function get(string $selector, $default = null)
    {
        self::getInstance();
        if (isset(self::$temporary[$selector])) {
            return self::$temporary[$selector];
        }

        return $default;
    }

    /**
     * take() returns the value of a key.
     * It is recommended that you use get() if you want to get single value.
     * This method is for getting nested an array or an object.
     *
     * キーに対応する値を返す
     * 単一の値を取得する場合はget()を使うことが推奨される
     * このメソッドは配列やオブジェクトの構造をまとめて取得するために使われる
     *
     * @param  string $selector Position of the key
     * @param  mixed  $default  Default value (in case the key is not found)
     * @return mixed
     */
    public static function take(string $selector, $default = null)
    {
        $instance = self::getInstance();
        if ($instance->buffer->isEmpty() === false) {
            $instance->rebuild(new Buffer);
        }
        $head = &self::$master;
        $arr = explode('.', $selector);
        foreach ($arr as $key) {
            if (!isset($head[$key])) {
                return $default;
            }
            $head = &$head[$key];
        }

        return $head;
    }

    /**
     * set() sets the value of a key.
     *
     * キーに値をセットする
     *
     * @param  string $selector Position of the key
     * @param  mixed  $value    Value of the key
     * @return mixed            New value of the key
     */
    public static function set(string $selector, $value)
    {
        self::getInstance()->buffer->pool('set', $selector, $value);
        self::$temporary[$selector] = $value;

        return $value;
    }

    /**
     * assign() sets the value of keys recursively.
     *
     * 再帰的にキーに値をセットする
     *
     * @param  array $mass Associative array
     * @return array
     */
    public static function assign(array $mass): array
    {
        $list = [];
        $instance = self::getInstance();
        $instance->walk($mass, '', $list);

        foreach ($list as $selector => $value) {
            $instance->set($selector, $value);
        }

        $instance->rebuild(new Buffer);

        return $mass;
    }

    /**
     * delete() unsets a key.
     *
     * キーを削除する
     *
     * @param  string $selector Position of the key
     * @return mixed            Value of the removed key
     */
    public static function delete(string $selector)
    {
        $value = self::get($selector);
        if (!is_null($value)) {
            unset(self::$temporary[$selector]);
            self::getInstance()->buffer->pool('delete', $selector);
        } else {
            $head = &self::$master;
            $arr = explode('.', $selector);
            foreach ($arr as $key) {
                if (!isset($head[$key])) {
                    return $value;
                }
                $head = &$head[$key];
            }

            $value = $head;
            $head = null;
            self::getInstance()->rebuild(new Buffer);
        }

        return $value;
    }

    public static function output()
    {
        return json_encode(self::$master, JSON_PRETTY_PRINT);
    }

    /**
     * walk() converts a nested master table into a temporary list.
     *
     * ネストされたマスターテーブルをテンポラリーリストに変換する
     *
     * @param  array  $data     Nested master table
     * @param  string $position Entry position
     * @param  array  &$table   Converted temporary list
     * @return void
     */
    private function walk(array $data, string $position, array &$table)
    {
        foreach ($data as $key => $value) {
            $current_position = $position . $key;

            // Associative array
            if (
                is_array($value) && array_values((array)$value) !== $value
            ) {
                $this->walk((array)$value, $current_position . '.', $table);
            // Indexed array
            } else {
                $table[$current_position] = $value;
                $current_position = $position;
            }
        }
    }

    /**
     * rebuild() let the buffer flush and rebuild the master table and the temporary list.
     *
     * バッファーを解放してマスターテーブルおよびテンポラリーリストを再構築する
     *
     * @param  Buffer $buffer Fresh buffer
     * @return void
     */
    private function rebuild(Buffer $buffer)
    {
        foreach ($this->buffer->pooled ?? [] as $p) {
            $head = &self::$master;
            $arr = explode('.', $p->selector);
            if ($p->action === 'set') {
                foreach ($arr as $key) {
                    if (!isset($head[$key])) {
                        $head[$key] = [];
                    }
                    $head = &$head[$key];
                }
                $head = $p->value;
            } elseif ($p->action === 'delete') {
                foreach ($arr as $key) {
                    if (!isset($head[$key])) {
                        break;
                    }
                    $head = &$head[$key];
                }
                $head = null;
            }

        }
        $this->buffer = $buffer;
        self::$temporary = [];
        $this->walk((array)self::$master, '', self::$temporary);
    }
}
