<?php

/*
 * The MIT License
 *
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

namespace MwbExporter\Formatter\Doctrine2\Model;

use MwbExporter\Model\ForeignKey;
use MwbExporter\Model\Table as BaseTable;
use MwbExporter\Formatter\Doctrine2\Formatter;

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
        if (($bundleNamespace = $this->parseComment('bundleNamespace')) || ($bundleNamespace = $this->getConfig()->get(Formatter::CFG_BUNDLE_NAMESPACE))) {
            $namespace = $bundleNamespace.'\\';
        }
        if ($entityNamespace = $this->getConfig()->get(Formatter::CFG_ENTITY_NAMESPACE)) {
            $namespace .= $entityNamespace;
        } else {
            $namespace .= 'Entity';
        }

        return $namespace;
    }

    public function getBaseEntityNamespace() {
        return 'Base\\'.$this->getEntityNamespace();
    }

    /**
     * Get the entity cacheMode.
     *
     * @return string
     */
    public function getEntityCacheMode()
    {
        $cacheMode = strtoupper(trim($this->parseComment('cache')));
        if (in_array($cacheMode, array('READ_ONLY', 'NONSTRICT_READ_WRITE', 'READ_WRITE'))) return $cacheMode;
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
        $result = array();
        if ($lifecycleCallbacks = trim($this->parseComment('lifecycleCallbacks'))) {
            foreach (explode("\n", $lifecycleCallbacks) as $callback) {
                list($method, $handler) = explode(':', $callback, 2);
                $method = lcfirst(trim($method));
                if (!in_array($method, array('postLoad', 'prePersist', 'postPersist', 'preRemove', 'postRemove', 'preUpdate', 'postUpdate'))) {
                    continue;
                }
                if (!isset($result[$method])) {
                    $result[$method] = array();
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
     * @param boolean $plural  Return plural form
     * @param ForeignKey $foreign The foreign key object
     * @param boolean $useM2OneName For foreign keys that have the relationNames tag, a flag to return the Many2One name
     * @return string
     */
    public function getRelatedVarName($name, $related = null, $plural = false, ForeignKey $foreign = null, $useM2OneName = false)
    {

        if ($foreign && $foreign->parseComment('relationNames')) {

            /**
             * If the foreign key is specified and it has a `relationNames` tag in the comments, use the values from this tag
             */

            list($oneToManyName, $manyToOneName) = explode(':', $foreign->parseComment('relationNames'), 2);

            if ($oneToManyName && $manyToOneName) {
                // Both names must be specified, otherwise the comment tag is not considered valid

                if ($useM2OneName) {
                    //the M2One name is requested - needed for the add/remove to collection methods
                    $name = $manyToOneName;
                }
                else {
                    //If the plural flag is sent, it means the M2One relation name is requested
                    if (!$plural) {
                        //this would be the name of the field in the model that has a foreign key (one-to-many relation)
                        $name = $oneToManyName;
                    } else {
                        //this would be the name of the field in the model that is referenced by the foreign key (it as a many-to-one relation)
                        $name = $manyToOneName;
                    }
                }

                return $plural ? $this->pluralize($name) : $this->singularize($name);
            }
        }

        /**
         * Check if the foreign key is from a m2m table and if so, parse the `relatedNames` tag to check for custom relation names
         */

        if ($foreign && ($m2mTable = $foreign->getTable())->isManyToMany()) {
            $nameFromCommentTag = '';
            $relatedNames = trim($m2mTable->parseComment('relatedNames'));

            if ('' !== $relatedNames) {
                foreach (explode("\n", $relatedNames) as $relationMap) {
                    list($toChange, $replacement) = explode(':', $relationMap, 2);
                    if ($name === $toChange) {
                        $nameFromCommentTag = $replacement;
                        break;
                    }
                }
                if ($nameFromCommentTag) {
                    return $plural ? $this->pluralize($nameFromCommentTag) : $this->singularize($nameFromCommentTag);
                }
            }
        }


        /**
         * if $name does not match the current ModelName (in case a relation column), check if the table comment includes the `relatedNames` tag
         * and parse that to see if for $name was provided a custom value
         */

        $nameFromCommentTag = '';
        $relatedNames = trim($this->parseComment('relatedNames'));

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
            $name = $related ? strtr($this->getConfig()->get(Formatter::CFG_RELATED_VAR_NAME_FORMAT), array('%name%' => $name, '%related%' => $related)) : $name;

        }

        return $plural ? $this->pluralize($name) : $this->singularize($name);
    }
}
