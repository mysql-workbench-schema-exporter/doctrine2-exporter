<?php

/*
 * The MIT License
 *
 * Copyright (c) 2012-2024 Toha <tohenk@yahoo.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace MwbExporter\Formatter\Doctrine2\Enum;

use MwbExporter\Formatter\Doctrine2\Annotation\Model\Column;
use MwbExporter\Writer\WriterInterface;

/**
 * Enum Generator for Doctrine2
 *
 * @author Your Name <your.email@example.com>
 */
class EnumGenerator
{
    /**
     * @var Column
     */
    protected $column;

    /**
     * @var string
     */
    protected $namespace;

    /**
     * Constructor
     *
     * @param Column $column
     * @param string $namespace
     */
    public function __construct(Column $column, $namespace)
    {
        $this->column = $column;
        $this->namespace = $namespace;
    }

    /**
     * Generate enum class
     *
     * @param WriterInterface $writer
     * @return $this
     */
    public function generate(WriterInterface $writer)
    {
        if (!$this->column->isEnum()) {
            return $this;
        }

        $enumClassName = $this->column->getEnumClassName();
        $enumValues = $this->column->getEnumValues();
        $tableName = $this->column->getTable()->getModelName();
        $columnName = $this->column->getBeautifiedColumnName();

        $writer
            ->open($this->getEnumFileName())
            ->write('<?php')
            ->write('')
            ->write('declare(strict_types=1);')
            ->write('')
            ->write('namespace %s;', $this->namespace)
            ->write('')
            ->write('use MyCLabs\\Enum\\Enum;')
            ->write('')
            ->commentStart()
                ->write('%s', $enumClassName)
                ->write('')
                ->write('This enum class is auto-generated for the %s.%s column.', $tableName, $columnName)
                ->write('It represents the possible values for the %s field.', $columnName)
                ->write('')
                ->write('@package %s', $this->namespace)
                ->write('@author Auto Generated')
                ->write('@license MIT')
            ->commentEnd()
            ->write('class %s extends Enum', $enumClassName)
            ->write('{')
            ->indent()
        ;

        // 生成枚举常量
        foreach ($enumValues as $value) {
            $constantName = $this->generateConstantName($value);
            $writer
                ->write('public const %s = \'%s\';', $constantName, $value)
            ;
        }

        $writer
            ->write('')
            ->commentStart()
                ->write('Get all available values.')
                ->write('')
                ->write('@return array')
            ->commentEnd()
            ->write('public static function getValues(): array')
            ->write('{')
            ->indent()
                ->write('return [')
            ;

        // 生成 getValues 方法
        foreach ($enumValues as $value) {
            $constantName = $this->generateConstantName($value);
            $writer->write('    self::%s,', $constantName);
        }

        $writer
                ->write('];')
            ->outdent()
            ->write('}')
            ->write('')
            ->commentStart()
                ->write('Get all available value labels.')
                ->write('')
                ->write('@return array')
            ->commentEnd()
            ->write('public static function getLabels(): array')
            ->write('{')
            ->indent()
                ->write('return [')
            ;

        // 生成 getLabels 方法
        foreach ($enumValues as $value) {
            $constantName = $this->generateConstantName($value);
            $label = $this->generateLabel($value);
            $writer->write('    self::%s => \'%s\',', $constantName, $label);
        }

        $writer
                ->write('];')
            ->outdent()
            ->write('}')
            ->write('')
            ->commentStart()
                ->write('Get label for current value.')
                ->write('')
                ->write('@return string')
            ->commentEnd()
            ->write('public function getLabel(): string')
            ->write('{')
            ->indent()
                ->write('$labels = self::getLabels();')
                ->write('return $labels[$this->getValue()] ?? $this->getValue();')
            ->outdent()
            ->write('}')
            ->outdent()
            ->write('}')
            ->close()
        ;

        return $this;
    }

    /**
     * Get enum file name
     *
     * @return string
     */
    protected function getEnumFileName()
    {
        $enumClassName = $this->column->getEnumClassName();
        $table = $this->column->getTable();
        
        return $table->getTableFileName(null, ['%entity%' => $enumClassName]);
    }

    /**
     * Generate constant name from value
     *
     * @param string $value
     * @return string
     */
    protected function generateConstantName($value)
    {
        // 将值转换为大写常量名
        $constantName = strtoupper($value);
        
        // 替换特殊字符
        $constantName = preg_replace('/[^A-Z0-9_]/', '_', $constantName);
        
        // 移除多余的下划线
        $constantName = preg_replace('/_+/', '_', $constantName);
        $constantName = trim($constantName, '_');
        
        return $constantName;
    }

    /**
     * Generate label from value
     *
     * @param string $value
     * @return string
     */
    protected function generateLabel($value)
    {
        // 将下划线或连字符替换为空格，然后首字母大写
        $label = str_replace(['_', '-'], ' ', $value);
        $label = ucwords(strtolower($label));
        
        return $label;
    }
} 