<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

class Shopware_Components_CsvIterator extends Enlight_Class implements Iterator
{
    public const DEFAULT_DELIMITER = ';';
    public const DEFAULT_LENGTH = 60000;

    /**
     * The CSV file handler.
     *
     * @var resource
     */
    private $_handler;

    /**
     * The delimiter of the CSV file.
     *
     * @var non-empty-string
     */
    private $_delimiter;

    /**
     * The delimiter of the CSV file.
     *
     * @var string
     */
    private $_newline = "\r\n";

    /**
     * The delimiter of the CSV file.
     *
     * @var string
     */
    private $_fieldmark = '"';

    /**
     * The row counter.
     *
     * @var int
     */
    private $_key;

    /**
     * The element that will be returned on each iteration.
     *
     * @var int|false|array|null
     */
    private $_current;

    /**
     * The element that will be returned on each iteration.
     *
     * @var int|false|null
     */
    private $_header;

    /**
     * This is the constructor. It try to open the CSV file.
     *
     * @param string           $filename  the full path of the CSV file
     * @param non-empty-string $delimiter the delimiter
     * @param int              $header
     *
     * @throws Exception
     */
    public function __construct($filename, $delimiter = self::DEFAULT_DELIMITER, $header = null)
    {
        $realPath = realpath($filename);

        if ($realPath === false) {
            throw new InvalidArgumentException(sprintf('Given file path "%s" does not exist', $filename));
        }

        $handler = fopen($realPath, 'r');
        if (!\is_resource($handler)) {
            throw new Exception(sprintf('The file "%s" cannot be opened', $realPath));
        }
        $this->_handler = $handler;

        $this->_newline = $this->getNewLineType();

        $this->_delimiter = $delimiter;
        if (empty($header)) {
            $this->_read();
            $this->_header = $this->_current;
        } else {
            $this->_header = $header;
        }
    }

    /**
     * This is the destructor. It close the CSV file.
     */
    public function __destruct()
    {
        fclose($this->_handler);
    }

    /**
     * @deprecated in 5.6, will be removed 5.8 without replacement
     *
     * @param string $fieldmark
     *
     * @return void
     */
    public function SetFieldmark($fieldmark)
    {
        $this->_fieldmark = $fieldmark;
    }

    /**
     * @return array<mixed, mixed>|int|false|null
     */
    public function GetHeader()
    {
        return $this->_header;
    }

    /**
     * This method move the file pointer to the next row.
     *
     * @return void
     *
     * @deprecated - Native return and parameter type will be added with Shopware 5.8
     */
    #[\ReturnTypeWillChange]
    public function next()
    {
        $this->_read();
        ++$this->_key;
    }

    /**
     * This method reset the file handler.
     *
     * @return void
     *
     * @deprecated - Native return and parameter type will be added with Shopware 5.8
     */
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        rewind($this->_handler);
        $this->_read();
        $this->_read();
        $this->_key = 1;
    }

    /**
     * This method returns the current row number.
     *
     * @return int|null
     *
     * @deprecated - Native return and parameter type will be added with Shopware 5.8
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->_key;
    }

    /**
     * This methods return the current CSV row data.
     *
     * @return array The row as a one-dimensional array
     *
     * @deprecated - Native return and parameter type will be added with Shopware 5.8
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        $data = [];
        foreach ($this->_header as $key => $name) {
            $data[$name] = (\is_array($this->_current) && isset($this->_current[$key])) ? $this->_current[$key] : '';
        }

        return $data;
    }

    /**
     * This method checks if the current row is readable.
     *
     * @return bool if the current row is readable
     *
     * @deprecated - Native return and parameter type will be added with Shopware 5.8
     */
    #[\ReturnTypeWillChange]
    public function valid()
    {
        return $this->_current !== false;
    }

    /**
     * Helper function to determine the newline type of the file
     *
     * @throws \Exception
     *
     * @return string Detected new line type
     */
    private function getNewLineType()
    {
        $newLineWin = "\r\n";
        $newLineNix = "\n";

        $pos = false;
        $content = '';
        while ($pos === false && !feof($this->_handler)) {
            $content .= fread($this->_handler, 1024);
            // Get first appearance of \n
            $pos = strpos($content, "\n");
        }

        if ($pos !== false && $pos > 1) {
            rewind($this->_handler);
            // Check if the previous char is a \r. If it is we have a windows EOL
            if ($content[$pos - 1] === "\r") {
                return $newLineWin;
            }

            return $newLineNix;
        }

        throw new Exception('New line detection failed');
    }

    /**
     * This method read the next row of the CSV file.
     *
     * @return void
     */
    private function _read()
    {
        if (feof($this->_handler)) {
            $this->_current = false;

            return;
        }

        $count = 0;
        $line = stream_get_line($this->_handler, self::DEFAULT_LENGTH, $this->_newline);
        if ($line === false) {
            $this->_current = false;

            return;
        }

        // Remove possible utf8-bom
        if (str_starts_with($line, pack('CCC', 0xEF, 0xBB, 0xBF))) {
            $line = substr($line, 3);
        }

        while ((empty($this->_fieldmark) || ($count = substr_count($line, $this->_fieldmark)) % 2 != 0) && !feof($this->_handler)) {
            $line .= $this->_newline . stream_get_line($this->_handler, self::DEFAULT_LENGTH, $this->_newline);
        }
        if (empty($line)) {
            $this->_current = false;

            return;
        }
        $line = explode($this->_delimiter, $line);
        if (empty($count) || !\is_array($line)) {
            $this->_current = $line;

            return;
        }
        $this->_current = [];
        $row = '';
        do {
            $row .= current($line);
            $count = substr_count($row, $this->_fieldmark);
            if ($count % 2 !== 0) {
                $row .= ';';
                continue;
            }

            if ($count) {
                $this->_current[] = str_replace($this->_fieldmark . $this->_fieldmark, $this->_fieldmark, substr($row, 1, -1));
            } else {
                $this->_current[] = $row;
            }
            $row = '';
        } while (next($line) !== false);
    }
}
