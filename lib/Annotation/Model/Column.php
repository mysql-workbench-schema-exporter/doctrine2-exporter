<?php

/*
 * The MIT License
 *
 * Copyright (c) 2010 Johannes Mueller <circus2(at)web.de>
 * Copyright (c) 2012-2014 Toha <tohenk@yahoo.com>
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

namespace MwbExporter\Formatter\Doctrine2\Annotation\Model;

use MwbExporter\Formatter\DatatypeConverterInterface;
use MwbExporter\Formatter\Doctrine2\Model\Column as BaseColumn;
use MwbExporter\Formatter\Doctrine2\Annotation\Formatter;
use MwbExporter\Writer\WriterInterface;

class Column extends BaseColumn
{
    public function getColumnName($raw = true)
    {
        $name = parent::getColumnName();

        return $raw ? $name : $this->getNaming($name);
    }

    private function getStringDefaultValue() {
        $defaultValue = $this->getDefaultValue();
        if (is_null($defaultValue) || 'CURRENT_TIMESTAMP' == $defaultValue) {
            $defaultValue = '';
        } else {
            if ($this->getColumnType() == DatatypeConverterInterface::DATATYPE_VARCHAR) {
                $defaultValue = " = $defaultValue";
            } elseif ($this->isBoolean()) {
                $defaultValue = " = ".($defaultValue == 0 ? 'false' : 'true');
            } else {
                $defaultValue = " = $defaultValue";
            }
        }

        return $defaultValue;
    }

    public function writeVar(WriterInterface $writer)
    {
        if (!$this->isIgnored()) {
            $useBehavioralExtensions = $this->getConfig()->get(Formatter::CFG_USE_BEHAVIORAL_EXTENSIONS);
            $isBehavioralColumn = strstr($this->getTable()->getName(), '_img') && $useBehavioralExtensions;
            $comment = $this->getComment();
            $columnName = $this->getColumnName(false);
            $writer
                ->write('/**')
                ->writeIf($comment, $comment)
                ->writeIf($this->isPrimary,
                        ' * '.$this->getTable()->getAnnotation('Id'))
                ->writeIf($useBehavioralExtensions && $this->getColumnName() === 'created_at',
                        ' * @Gedmo\Timestampable(on="create")')
                ->writeIf($useBehavioralExtensions && $this->getColumnName() === 'updated_at',
                        ' * @Gedmo\Timestampable(on="update")')
                ->write(' * '.$this->getTable()->getAnnotation('Column', $this->asAnnotation()))
                ->writeIf($this->isAutoIncrement(),
                        ' * '.$this->getTable()->getAnnotation('GeneratedValue', array('strategy' => strtoupper($this->getConfig()->get(Formatter::CFG_GENERATED_VALUE_STRATEGY)))))
                ->writeIf($isBehavioralColumn && strstr($this->getColumnName(), 'path'),
                        ' * @Gedmo\UploadableFilePath')
                ->writeIf($isBehavioralColumn && strstr($this->getColumnName(), 'name'),
                        ' * @Gedmo\UploadableFileName')
                ->writeIf($isBehavioralColumn && strstr($this->getColumnName(), 'mime'),
                        ' * @Gedmo\UploadableFileMimeType')
                ->writeIf($isBehavioralColumn && strstr($this->getColumnName(), 'size'),
                        ' * @Gedmo\UploadableFileSize')
                ->write(' */')
                ->write('protected $'.$columnName.$this->getStringDefaultValue().';')
                ->write('')
            ;
        }

        return $this;
    }

    public function writeGetterAndSetter(WriterInterface $writer)
    {
        if (!$this->isIgnored()) {
            $this->getDocument()->addLog(sprintf('  Writing setter/getter for column "%s"', $this->getColumnName()));

            $table = $this->getTable();
            $converter = $this->getFormatter()->getDatatypeConverter();
            $nativeType = $converter->getNativeType($converter->getMappedType($this));
            $columnName = $this->getColumnName(false);

            $typehints = array(
                'set_phpdoc_arg' => $this->typehint($nativeType, !$this->isNotNull()),
                'set_phpdoc_return' => $this->typehint($table->getNamespace(), false),
                'set_arg' => $this->paramTypehint($nativeType, !$this->isNotNull()),
                'set_return' => $this->returnTypehint(null, false),

                'get_phpdoc' => $this->typehint($nativeType, !$this->isNotNull()),
                'get_return' => $this->returnTypehint($nativeType, null === $this->getDefaultValue() ? true : !$this->isNotNull()),
            );

            $writer
                // setter
                ->write('/**')
                ->write(' * Set the value of '.$columnName.'.')
                ->write(' *')
                ->write(' * @param '.$typehints['set_phpdoc_arg'].' $'.$columnName)
                ->write(' *')
                ->write(' * @return '.$typehints['set_phpdoc_return'])
                ->write(' */')
                ->write('public function set'.$this->getBeautifiedColumnName().'('.$typehints['set_arg'].'$'.$columnName.')'.$typehints['set_return'])
                ->write('{')
                ->indent()
                    ->write('$this->'.$columnName.' = $'.$columnName.';')
                    ->write('')
                    ->write('return $this;')
                ->outdent()
                ->write('}')
                ->write('')
                // getter
                ->write('/**')
                ->write(' * Get the value of '.$columnName.'.')
                ->write(' *')
                ->write(' * @return '.$typehints['get_phpdoc'])
                ->write(' */')
                ->write('public function '.$this->getColumnGetterName().'()'.$typehints['get_return'])
                ->write('{')
                ->indent()
                    ->write('return $this->'.$columnName.';')
                ->outdent()
                ->write('}')
                ->write('')
            ;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function asAnnotation()
    {
        $columnName = $this->getColumnName(false);
        $attributes = array(
            'name' => ($quotedColumnName = $this->getTable()->quoteIdentifier($this->getColumnName())) !== $columnName ? $quotedColumnName : null,
            'type' => $this->getFormatter()->getDatatypeConverter()->getMappedType($this),
        );
        if (($length = $this->parameters->get('length')) && ($length != -1)) {
            $attributes['length'] = (int) $length;
        }
        if (($precision = $this->parameters->get('precision')) && ($precision != -1) && ($scale = $this->parameters->get('scale')) && ($scale != -1)) {
            $attributes['precision'] = (int) $precision;
            $attributes['scale'] = (int) $scale;
        }
        if ($this->isNullableRequired()) {
            $attributes['nullable'] = $this->getNullableValue();
        }

        $attributes['options'] = [];
        if ($this->isUnsigned()) {
            $attributes['options'] = array('unsigned' => true);
        }

        if ('json' === $attributes['type']) {
            $attributes['options']['jsonb'] = true;
        }

        $rawDefaultValue = $this->parameters->get('defaultValue') == 'NULL' ? null : $this->parameters->get('defaultValue');
        if ($rawDefaultValue !== '') {
            $attributes['options']['default'] = $rawDefaultValue === '' ? null : $rawDefaultValue;
        }

        if (count($attributes['options']) == 0) {
            unset($attributes['options']);
        }

        return $attributes;
    }

    protected function typehint($type, $nullable)
    {
        if (strlen($type)) {
            $type = strtr($type, array('integer' => 'int', 'boolean' => 'bool'));
            if ($this->getConfig()->get(Formatter::CFG_PHP7_TYPEHINTS)) {
                if ($nullable || '\DateTime' === $type) {
                    $type = '?'.$type;
                }
            }
        }

        return $type;
    }

    protected function isTypehintSkipped()
    {
        return in_array($this->getTable()->getName().'.'.$this->getColumnName(), $this->getConfig()->get(Formatter::CFG_PHP7_SKIPPED_COLUMNS_TYPEHINTS));
    }

    protected function paramTypehint($type, $nullable)
    {
        if ($this->getConfig()->get(Formatter::CFG_PHP7_TYPEHINTS) &&
            $this->getConfig()->get(Formatter::CFG_PHP7_ARG_TYPEHINTS) &&
            !$this->isTypehintSkipped() &&
            strlen($type)) {
            return $this->typehint($type, $nullable).' ';
        }
    }

    protected function returnTypehint($type, $nullable)
    {
        if ($this->getConfig()->get(Formatter::CFG_PHP7_TYPEHINTS) &&
            $this->getConfig()->get(Formatter::CFG_PHP7_RETURN_TYPEHINTS) &&
            !$this->isTypehintSkipped() &&
            strlen($type)) {
            return ': '.$this->typehint($type, $nullable);
        }
    }
}
