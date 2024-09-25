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

namespace MwbExporter\Formatter\Doctrine2\Model;

use MwbExporter\Formatter\Doctrine2\Configuration\BundleNamespace as BundleNamespaceConfiguration;
use MwbExporter\Formatter\Doctrine2\Configuration\EntityNamespace as EntityNamespaceConfiguration;
use MwbExporter\Formatter\Doctrine2\Configuration\RelatedVarName as RelatedVarNameConfiguration;
use MwbExporter\Model\Table as BaseTable;

class Table extends BaseTable
{
    /**
     * Get the entity namespace.
     *
     * @return string
     */
    public function getEntityNamespace()
    {
        $namespace = '';
        if (($bundleNamespace = $this->parseComment('bundleNamespace')) || ($bundleNamespace = $this->getConfig(BundleNamespaceConfiguration::class)->getValue())) {
            $namespace = $bundleNamespace.'\\';
        }
        if ($entityNamespace = $this->getConfig(EntityNamespaceConfiguration::class)->getValue()) {
            $namespace .= $entityNamespace;
        } else {
            $namespace .= 'Entity';
        }

        return $namespace;
    }

    public function getBaseEntityNamespace()
    {
        return 'Base\\'.$this->getEntityNamespace();
    }

    /**
     * Get the entity cacheMode.
     *
     * @return string
     */
    public function getEntityCacheMode()
    {
        $cacheMode = strtoupper(trim((string) $this->parseComment('cache')));
        if (in_array($cacheMode, ['READ_ONLY', 'NONSTRICT_READ_WRITE', 'READ_WRITE'])) {
            return $cacheMode;
        }
    }

    /**
     * Get namespace of a class.
     *
     * @param string $class The class name
     * @return string
     */
    public function getNamespace($class = null, $absolute = true, $base = false)
    {
        return sprintf(
            '%s%s\%s',
            $absolute ? '\\' : '',
            $base ? $this->getBaseEntityNamespace() : $this->getEntityNamespace(),
            null === $class ? $this->getModelName() : $class
        );
    }

    /**
     * Get Model Name in FQCN format. If reference namespace is suplied and the entity namespace
     * is equal then relative model name returned instead.
     *
     * @param string $referenceNamespace The reference namespace
     * @return string
     */
    public function getModelNameAsFQCN($referenceNamespace = null)
    {
        $namespace = $this->getEntityNamespace();
        $fqcn = ($namespace == $referenceNamespace) ? false : true;

        return $fqcn ? $namespace.'\\'.$this->getModelName() : $this->getModelName();
    }

    /**
     * Get lifecycleCallbacks.
     *
     * @return array
     */
    public function getLifecycleCallbacks()
    {
        $result = [];
        if ($lifecycleCallbacks = trim((string) $this->parseComment('lifecycleCallbacks'))) {
            foreach (explode("\n", $lifecycleCallbacks) as $callback) {
                list($method, $handler) = explode(':', $callback, 2);
                $method = lcfirst(trim($method));
                if (!in_array($method, ['postLoad', 'prePersist', 'postPersist', 'preRemove', 'postRemove', 'preUpdate', 'postUpdate'])) {
                    continue;
                }
                if (!isset($result[$method])) {
                    $result[$method] = [];
                }
                $result[$method][] = trim($handler);
            }
        }

        return $result;
    }

    /**
     * Get identifier name formatting.
     *
     * @param string $name  Identifier name
     * @param string $related  Related name
     * @param string $plural  Return plural form
     * @return string
     */
    public function getRelatedVarName($name, $related = null, $plural = false)
    {
        /**
         * if $name does not match the current ModelName (in case a relation column), check if the table comment includes the `relatedNames` tag
         * and parse that to see if for $name was provided a custom value
         */

        $nameFromCommentTag = '';
        $relatedNames = trim((string) $this->parseComment('relatedNames'));

        if ('' !== $relatedNames) {
            foreach (explode("\n", $relatedNames) as $relationMap) {
                list($toChange, $replacement) = explode(':', $relationMap, 2);
                if ($name === $toChange) {
                    $nameFromCommentTag = $replacement;
                    break;
                }
            }
        }
        if ($nameFromCommentTag) {
            $name = $nameFromCommentTag;
        } else {
            $name = $related ? strtr($this->getConfig(RelatedVarNameConfiguration::class)->getValue(), ['%name%' => $name, '%related%' => $related]) : $name;
        }

        return $plural ? $this->pluralize($name) : $name;
    }
}
