<?php

/*
 * The MIT License
 *
 * Copyright (c) 2010 Johannes Mueller <circus2(at)web.de>
 * Copyright (c) 2012-2023 Toha <tohenk@yahoo.com>
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

namespace MwbExporter\Formatter\Doctrine2\Yaml\Model;

use MwbExporter\Formatter\DatatypeConverterInterface;
use MwbExporter\Formatter\Doctrine2\Configuration\GeneratedValueStrategy as GeneratedValueStrategyConfiguration;
use MwbExporter\Formatter\Doctrine2\Model\Column as BaseColumn;
use MwbExporter\Formatter\Doctrine2\Yaml\Formatter;

class Column extends BaseColumn
{
    public function asYAML()
    {
        $values = [];
        $values['type'] = $this->getFormatter()->getDatatypeConverter()->getMappedType($this);
        if (($length = $this->getParameters()->get('length')) && ($length != -1)) {
            $values['length'] = (int) $length;
        }
        if (($precision = $this->getParameters()->get('precision')) && ($precision != -1) && ($scale = $this->getParameters()->get('scale')) && ($scale != -1)) {
            $values['precision'] = (int) $precision;
            $values['scale'] = (int) $scale;
        }
        if ($this->isNullableRequired()) {
            $values['nullable'] = $this->getNullableValue();
        }
        if ($this->isUnsigned()) {
            $values['unsigned'] = true;
        }
        if ($this->isAutoIncrement()) {
            $values['generator'] = ['strategy' => strtoupper($this->getConfig(GeneratedValueStrategyConfiguration::class)->getValue())];
        }
        if ($this->getDefaultValue() !== null) {
            $values['options']['default'] = $this->isStringType()
                ? "'" . $this->getDefaultValue() . "'"
                : $this->getDefaultValue() ;
        }

        return $values;
    }

    /**
     * Get if the type is a string or not.
     * @return bool Return true if the datatype is a string, else return false.
     */
    private function isStringType()
    {
        switch($this->getColumnType()) {
            case DatatypeConverterInterface::DATATYPE_CHAR:
            case DatatypeConverterInterface::DATATYPE_VARCHAR:
                return true;
            default:
                return false;
        }
    }
}
