<?php

/*
 * The MIT License
 *
 * Copyright (c) 2010 Johannes Mueller <circus2(at)web.de>
 * Copyright (c) 2012-2024 Toha <tohenk@yahoo.com>
 * Copyright (c) 2013 WitteStier <development@wittestier.nl>
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

namespace MwbExporter\Formatter\Doctrine2\ZF2InputFilterAnnotation\Model;

use MwbExporter\Formatter\Doctrine2\Annotation\Model\Table as BaseTable;
use MwbExporter\Formatter\Doctrine2\ZF2InputFilterAnnotation\Configuration\EntityArrayCopy as EntityArrayCopyConfiguration;
use MwbExporter\Formatter\Doctrine2\ZF2InputFilterAnnotation\Configuration\EntityPopulate as EntityPopulateConfiguration;
use MwbExporter\Writer\WriterInterface;

class Table extends BaseTable
{
    protected function getClassImplementations()
    {
        return 'InputFilterAwareInterface';
    }

    protected function includeUses()
    {
        parent::includeUses();
        $this->usedClasses = array_merge($this->usedClasses, [
            'Zend\InputFilter\InputFilter',
            'Zend\InputFilter\Factory as InputFactory',
            'Zend\InputFilter\InputFilterAwareInterface',
            'Zend\InputFilter\InputFilterInterface',
        ]);
    }

    public function writePreClassHandler(WriterInterface $writer)
    {
        $writer
            ->commentStart()
                ->write('Instance of InputFilterInterface.')
                ->write('')
                ->write('@var InputFilter')
            ->commentEnd()
            ->write('private $inputFilter;')
            ->write('')
        ;

        return $this;
    }

    public function writePostClassHandler(WriterInterface $writer)
    {
        $this->writeInputFilter($writer);
        if ($this->getConfig(EntityPopulateConfiguration::class)->getValue()) {
            $this->writePopulate($writer);
        }
        if ($this->getConfig(EntityArrayCopyConfiguration::class)->getValue()) {
            $this->writeGetArrayCopy($writer);
        }

        return $this;
    }

    /**
     * Write \Zend\InputFilter\InputFilterInterface methods.
     * http://framework.zend.com/manual/2.1/en/modules/zend.input-filter.intro.html
     *
     * @param \MwbExporter\Writer\WriterInterface $writer
     * @return \MwbExporter\Formatter\Doctrine2\ZF2InputFilterAnnotation\Model\Table
     */
    public function writeInputFilter(WriterInterface $writer)
    {
        $writer
            ->commentStart()
                ->write('Not used, Only defined to be compatible with InputFilterAwareInterface.')
                ->write('')
                ->write('@param \Zend\InputFilter\InputFilterInterface $inputFilter')
                ->write('@throws \Exception')
            ->commentEnd()
            ->write('public function setInputFilter(InputFilterInterface $inputFilter)')
            ->write('{')
            ->indent()
                ->write('throw new \Exception("Not used.");')
            ->outdent()
            ->write('}')
            ->write('')
            ->commentStart()
                ->write('Return a for this entity configured input filter instance.')
                ->write('')
                ->write('@return InputFilterInterface')
            ->commentEnd()
            ->write('public function getInputFilter()')
            ->write('{')
            ->indent()
                ->write('if ($this->inputFilter instanceof InputFilterInterface) {')
                ->indent()
                    ->write('return $this->inputFilter;')
                ->outdent()
            ->write('}')
            ->write('$factory = new InputFactory();')
            ->write('$filters = [')
            ->indent()
                ->writeCallback(function(WriterInterface $writer, Table $_this = null) {
                    $_this->writeInputFilterColumns($writer);
                })
            ->outdent()
            ->write('];')
            ->write('$this->inputFilter = $factory->createInputFilter($filters);')
            ->write('')
            ->write('return $this->inputFilter;')
            ->outdent()
            ->write('}')
            ->write('')
        ;

        return $this;
    }

    public function writeInputFilterColumns(WriterInterface $writer)
    {
        foreach ($this->getColumns() as $column) {
            // by type
            switch ($this->getFormatter()->getDatatypeConverter()->getDataType($column->getColumnType())) {
                case 'string':
                    $s_filters = '[
                    [\'name\' => \'Zend\Filter\StripTags\'],
                    [\'name\' => \'Zend\Filter\StringTrim\'],
                ]';
                    $s_validators = sprintf('[
                    [
                        \'name\' => \'Zend\Validator\StringLength\',
                        \'options\' => [
                            \'encoding\' => \'UTF-8\',
                            \'min\' => %s,
                            \'max\' => ' . $column->getLength() . '
                        ],
                    ],
                ]', $column->isNotNull() ? '1' : '0');
                    break;
                case 'smallint':
                case 'integer':
                    $s_filters = '[
                    [\'name\' => \'Zend\Filter\ToInt\'],
                ]';
                    $s_validators = '[
                    [\'name\' => \'Zend\I18n\Validator\IsInt\'],
                ]';
                    break;
                case 'boolean':
                    $s_filters = '[
                    [\'name\' => \'Zend\Filter\Boolean\'],
                ]';
                    $s_validators = '[]';
                    break;
                case 'datetime':
                    $s_filters = '[]';
                    $s_validators = '[]';
                    break;
                case 'float':
                    $s_filters = '[
                    [\'name\' => \'Zend\I18n\Filter\NumberFormat\'],
                ]';
                    $s_validators = '[
                    [\'name\' => \'Zend\I18n\Validator\IsFloat\'],
                ]';
                    break;
                case 'decimal':
                    $s_filters = '[
                    [\'name\' => \'Zend\Filter\Digits\'],
                ]';
                    $s_validators = '[
                    [\'name\' => \'Zend\Validator\Digits\'],
                ]';
                    break;
                case 'text':
                    $s_filters = '[
                ]';
                    if ($column->getLength() > 0) {
                        $s_validators = sprintf('[
                            [
                                \'name\' => \'Zend\Validator\StringLength\',
                                \'options\' => [
                                    \'encoding\' => \'UTF-8\',
                                    \'min\' => %s,
                                    \'max\' => ' . $column->getLength() . '
                                ],
                            ],
                        ]', $column->isNotNull() ? '1' : '0');
                    } else {
                        $s_validators = '[]';
                    }
                    break;
                default:
                    $s_filters = '[]';
                    $s_validators = '[]';
                    break;
            }

            // by name
            if (strstr($column->getColumnName(), 'phone') or strstr($column->getColumnName(), '_tel')) {
                $s_validators = '[
                            [\'name\' => \'Zend\I18n\Validator\PhoneNumber\'],
                        ]';
            } elseif (strstr($column->getColumnName(), 'email')) {
                $s_validators = '[
                            [\'name\' => \'Zend\Validator\EmailAddress\'],
                        ]';
            } elseif (strstr($column->getColumnName(), 'postcode') or strstr($column->getColumnName(), '_zip')) {
                $s_validators = '[
                            [\'name\' => \'Zend\I18n\Validator\PostCode\'],
                        ]';
            }

            $writer
                ->write('[')
                ->indent()
                ->write('\'name\' => \'%s\',', $column->getColumnName())
                ->write('\'required\' => %s,', $column->isNotNull() && !$column->isPrimary() ? 'true' : 'false')
                ->write('\'filters\' => %s,', $s_filters)
                ->write('\'validators\' => %s,', $s_validators)
                ->outdent()
                ->write('],')
            ;
        }

        return $this;
    }

    /**
     * Write entity populate method.
     *
     * @see \Zend\Stdlib\Hydrator\ArraySerializable::extract()
     * @param \MwbExporter\Writer\WriterInterface $writer
     * @return \MwbExporter\Formatter\Doctrine2\ZF2InputFilterAnnotation\Model\Table
     */
    public function writePopulate(WriterInterface $writer)
    {
        $writer
            ->commentStart()
                ->write('Populate entity with the given data.')
                ->write('The set* method will be used to set the data.')
                ->write('')
                ->write('@param array $data')
                ->write('@return boolean')
            ->commentEnd()
            ->write('public function populate(array $data = [])')
            ->write('{')
            ->indent()
                ->write('foreach ($data as $field => $value) {')
                ->indent()
                    ->write('$setter = sprintf(\'set%s\', ucfirst(')
                    ->indent()
                        ->write('str_replace(\' \', \'\', ucwords(str_replace(\'_\', \' \', $field)))')
                    ->outdent()
                    ->write('));')
                    ->write('if (method_exists($this, $setter)) {')
                    ->indent()
                        ->write('$this->{$setter}($value);')
                    ->outdent()
                    ->write('}')
                ->outdent()
                ->write('}')
                ->write('')
                ->write('return true;')
            ->outdent()
            ->write('}')
            ->write('')
        ;

        return $this;
    }

    /**
     * Write getArrayCopy method.
     *
     * @see \Zend\Stdlib\Hydrator\ArraySerializable::hydrate()
     * @param \MwbExporter\Writer\WriterInterface $writer
     * @return \MwbExporter\Formatter\Doctrine2\ZF2InputFilterAnnotation\Model\Table
     */
    public function writeGetArrayCopy(WriterInterface $writer)
    {
        $columns = $this->getColumns();
        $relations = $this->getTableRelations();

        $writer
            ->commentStart()
                ->write('Return a array with all fields and data.')
                ->write('Default the relations will be ignored.')
                ->write('')
                ->write('@param array $fields')
                ->write('@return array')
            ->commentEnd()
            ->write('public function getArrayCopy(array $fields = [])')
            ->write('{')
            ->indent()
                ->write('$dataFields = [%s];', implode(', ', array_map(function($column) {
                    return sprintf('\'%s\'', $column);
                }, $columns->getColumnNames())))
                ->write('$relationFields = [%s];', implode(', ', array_map(function($relation) {
                    return sprintf('\'%s\'', lcfirst($relation->getReferencedTable()->getModelName()));
                }, $relations)))
                ->write('$copiedFields = [];')
                ->write('foreach ($relationFields as $relationField) {')
                ->indent()
                    ->write('$map = null;')
                    ->write('if (array_key_exists($relationField, $fields)) {')
                    ->indent()
                        ->write('$map = $fields[$relationField];')
                        ->write('$fields[] = $relationField;')
                        ->write('unset($fields[$relationField]);')
                    ->outdent()
                    ->write('}')
                    ->write('if (!in_array($relationField, $fields)) {')
                    ->indent()
                        ->write('continue;')
                    ->outdent()
                    ->write('}')
                    ->write('$getter = sprintf(\'get%s\', ucfirst(str_replace(\' \', \'\', ucwords(str_replace(\'_\', \' \', $relationField)))));')
                    ->write('$relationEntity = $this->{$getter}();')
                    ->write('$copiedFields[$relationField] = (!is_null($map))')
                    ->indent()
                        ->write('? $relationEntity->getArrayCopy($map)')
                        ->write(': $relationEntity->getArrayCopy();')
                    ->outdent()
                    ->write('$fields = array_diff($fields, [$relationField]);')
                ->outdent()
                ->write('}')
                ->write('foreach ($dataFields as $dataField) {')
                ->indent()
                    ->write('if (!in_array($dataField, $fields) && !empty($fields)) {')
                    ->indent()
                        ->write('continue;')
                    ->outdent()
                    ->write('}')
                    ->write('$getter = sprintf(\'get%s\', ucfirst(str_replace(\' \', \'\', ucwords(str_replace(\'_\', \' \', $dataField)))));')
                    ->write('$copiedFields[$dataField] = $this->{$getter}();')
                ->outdent()
                ->write('}')
                ->write('')
                ->write('return $copiedFields;')
            ->outdent()
            ->write('}')
            ->write('')
        ;

        return $this;
    }
}
