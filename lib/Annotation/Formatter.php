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

namespace MwbExporter\Formatter\Doctrine2\Annotation;

use MwbExporter\Configuration\Indentation as IndentationConfiguration;
use MwbExporter\Formatter\Doctrine2\Annotation\Configuration\AnnotationPrefix as AnnotationPrefixConfiguration;
use MwbExporter\Formatter\Doctrine2\Annotation\Configuration\BehavioralExtension as BehavioralExtensionConfiguration;
use MwbExporter\Formatter\Doctrine2\Annotation\Configuration\ClassExtend as ClassExtendConfiguration;
use MwbExporter\Formatter\Doctrine2\Annotation\Configuration\EntityExtend as EntityExtendConfiguration;
use MwbExporter\Formatter\Doctrine2\Annotation\Configuration\EntityExtendDiscriminator as EntityExtendDiscriminatorConfiguration;
use MwbExporter\Formatter\Doctrine2\Annotation\Configuration\EntitySerialize as EntitySerializeConfiguration;
use MwbExporter\Formatter\Doctrine2\Annotation\Configuration\GetterSetterSkip as GetterSetterSkipConfiguration;
use MwbExporter\Formatter\Doctrine2\Annotation\Configuration\IdentifierQuotingStrategy as IdentifierQuotingStrategyConfiguration;
use MwbExporter\Formatter\Doctrine2\Annotation\Configuration\Typehint as TypehintConfiguration;
use MwbExporter\Formatter\Doctrine2\Annotation\Configuration\TypehintArgument as TypehintArgumentConfiguration;
use MwbExporter\Formatter\Doctrine2\Annotation\Configuration\TypehintReturnValue as TypehintReturnValueConfiguration;
use MwbExporter\Formatter\Doctrine2\Annotation\Configuration\TypehintSkip as TypehintSkipConfiguration;
use MwbExporter\Formatter\Doctrine2\Formatter as BaseFormatter;
use MwbExporter\Model\Base;

class Formatter extends BaseFormatter
{
    protected function init()
    {
        parent::init();
        $this->getConfigurations()
            ->add(new AnnotationPrefixConfiguration())
            ->add(new ClassExtendConfiguration())
            ->add(new GetterSetterSkipConfiguration())
            ->add(new IdentifierQuotingStrategyConfiguration())
            ->add(new EntitySerializeConfiguration())
            ->add(new EntityExtendConfiguration())
            ->add(new EntityExtendDiscriminatorConfiguration())
            ->add(new BehavioralExtensionConfiguration())
            ->add(new TypehintConfiguration())
            ->add(new TypehintArgumentConfiguration())
            ->add(new TypehintReturnValueConfiguration())
            ->add(new TypehintSkipConfiguration())
            ->merge([
                IndentationConfiguration::class => 4,
            ], true)
        ;
    }

    /**
     * (non-PHPdoc)
     * @see \MwbExporter\Formatter\Formatter::createDatatypeConverter()
     */
    protected function createDatatypeConverter()
    {
        return new DatatypeConverter();
    }

    /**
     * (non-PHPdoc)
     * @see \MwbExporter\Formatter\Formatter::createTable()
     */
    public function createTable(Base $parent, $node)
    {
        return new Model\Table($parent, $node);
    }

    /**
     * (non-PHPdoc)
     * @see \MwbExporter\Formatter\FormatterInterface::createColumn()
     */
    public function createColumn(Base $parent, $node)
    {
        return new Model\Column($parent, $node);
    }

    public function getTitle()
    {
        return 'Doctrine 2.0 Annotation Classes';
    }

    public function getFileExtension()
    {
        return 'php';
    }

    /**
     * Get configuration scope.
     *
     * @return string
     */
    public static function getScope()
    {
        return 'Doctrine 2.0 Annotation';
    }
}
