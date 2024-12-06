<?php

/*
 * The MIT License
 *
 * Copyright (c) 2010 Johannes Mueller <circus2(at)web.de>
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

namespace MwbExporter\Formatter\Doctrine2\Yaml\Model;

use MwbExporter\Configuration\Comment as CommentConfiguration;
use MwbExporter\Configuration\Header as HeaderConfiguration;
use MwbExporter\Configuration\Indentation as IndentationConfiguration;
use MwbExporter\Configuration\Language as LanguageConfiguration;
use MwbExporter\Configuration\M2MSkip as M2MSkipConfiguration;
use MwbExporter\Formatter\Doctrine2\Configuration\AutomaticRepository as AutomaticRepositoryConfiguration;
use MwbExporter\Formatter\Doctrine2\Configuration\RepositoryNamespace as RepositoryNamespaceConfiguration;
use MwbExporter\Formatter\Doctrine2\Configuration\TableNamePrefix as TableNamePrefixConfiguration;
use MwbExporter\Formatter\Doctrine2\Model\Table as BaseTable;
use MwbExporter\Helper\Comment;
use MwbExporter\Model\ForeignKey;
use MwbExporter\Writer\WriterInterface;
use NTLAB\Object\YAML;

/**
 * @method \MwbExporter\Formatter\Doctrine2\Yaml\Formatter getFormatter()
 */
class Table extends BaseTable
{
    public function writeTable(WriterInterface $writer)
    {
        switch (true) {
            case $this->isExternal():
                return self::WRITE_EXTERNAL;
            case $this->getConfig(M2MSkipConfiguration::class)->getValue() && $this->isManyToMany():
                return self::WRITE_M2M;
            default:
                $this->getDocument()->addLog(sprintf('Writing table "%s"', $this->getModelName()));
                $writer
                    ->open($this->getTableFileName())
                    ->writeCallback(function(WriterInterface $writer, ?Table $_this = null) {
                        /** @var \MwbExporter\Configuration\Header $header */
                        $header = $this->getConfig(HeaderConfiguration::class);
                        if ($content = $header->getHeader()) {
                            $writer
                                ->write($_this->getFormatter()->getFormattedComment($content, Comment::FORMAT_YAML, null))
                                ->write('')
                            ;
                        }
                        if ($_this->getConfig(CommentConfiguration::class)->getValue()) {
                            $writer
                                ->write($_this->getFormatter()->getComment(Comment::FORMAT_YAML))
                                ->write('')
                            ;
                        }
                    })
                    ->write($this->asYAML())
                    ->close()
                ;

                return self::WRITE_OK;
        }
    }

    public function asYAML()
    {
        $namespace = $this->getNamespace(null, false);

        $values = [
            'type' => 'entity',
            'table' => $this->getConfig(TableNamePrefixConfiguration::class)->getValue().$this->getRawTableName(),
        ];
        // cache Mode
        if (!is_null($cacheMode = $this->getEntityCacheMode())) {
            $values['cache'] = [];
            $values['cache']['usage'] = $cacheMode;
        }
        if ($this->getConfig(AutomaticRepositoryConfiguration::class)->getValue()) {
            if ($repositoryNamespace = $this->getConfig(RepositoryNamespaceConfiguration::class)->getValue()) {
                $repositoryNamespace .= '\\';
            }
            $values['repositoryClass'] = $repositoryNamespace.$this->getModelName().'Repository';
        }
        // indices
        $this->getIndicesAsYAML($values, 'indexes');
        // columns => ids & fields
        $this->getColumnsAsYAML($values);
        // uniques
        $this->getIndicesAsYAML($values, 'uniqueConstraints');
        // table relations
        $this->getRelationsAsYAML($values);
        // table m2m relations
        $this->getM2MRelationsAsYAML($values);
        // lifecycle callback
        if (count($lifecycleCallbacks = $this->getLifecycleCallbacks())) {
            $values['lifecycleCallbacks'] = $lifecycleCallbacks;
        }
        /** @var \MwbExporter\Configuration\Indentation $indentation */
        $indentation = $this->getConfig(IndentationConfiguration::class);

        return new YAML([$namespace => $values], ['indentation' => $indentation->getIndentation(1), 'skip_null' => true]);
    }

    protected function getIndicesAsYAML(&$values, $type = 'indexes')
    {
        foreach ($this->getTableIndices() as $index) {
            switch (true) {
                case $type === 'indexes' && $index->isIndex():
                case $type === 'uniqueConstraints' && $index->isUnique():
                    if (!isset($values[$type])) {
                        $values[$type] = [];
                    }
                    $values[$type][$index->getName()] = ['columns' => $index->getColumnNames()];
                    break;
            }
        }

        return $this;
    }

    protected function getColumnsAsYAML(&$values)
    {
        foreach ($this->getColumns() as $column) {
            if ($column->isPrimary()) {
                if (!isset($values['id'])) {
                    $values['id'] = [];
                }
                $values['id'][$column->getColumnName()] = $column->asYAML();
            } else {
                if ($column->isIgnored()) {
                    continue;
                }
                if (!isset($values['fields'])) {
                    $values['fields'] = [];
                }
                $values['fields'][$column->getColumnName()] = $column->asYAML();
            }
        }

        return $this;
    }

    protected function getRelationsAsYAML(&$values)
    {
        // 1 <=> ? references
        foreach ($this->getAllLocalForeignKeys() as $local) {
            if ($this->isLocalForeignKeyIgnored($local)) {
                continue;
            }
            $targetEntity = $local->getOwningTable()->getModelName();
            $targetEntityFQCN = $local->getOwningTable()->getModelNameAsFQCN($local->getReferencedTable()->getEntityNamespace());
            $mappedBy = $local->getReferencedTable()->getModelName();
            $related = $local->getForeignM2MRelatedName();

            $this->getDocument()->addLog(sprintf('  Writing 1 <=> ? relation "%s"', $targetEntity));

            if ($local->isManyToOne()) {
                $this->getDocument()->addLog('  Relation considered as "1 <=> N"');

                $type = 'oneToMany';
                $relationName = lcfirst($this->getRelatedVarName($targetEntity, $related, true));
                if (!isset($values[$type])) {
                    $values[$type] = [];
                }
                $values[$type][$relationName] = array_merge([
                    'targetEntity' => $targetEntity,
                    'mappedBy' => lcfirst($this->getRelatedVarName($mappedBy, $related)),
                    'cascade' => $this->getFormatter()->getCascadeOption($local->parseComment('cascade')),
                    'fetch' => $this->getFormatter()->getFetchOption($local->parseComment('fetch')),
                    'orphanRemoval' => $this->getFormatter()->getBooleanOption($local->parseComment('orphanRemoval')),
                ], $this->getJoins($local));
            } else {
                $this->getDocument()->addLog('  Relation considered as "1 <=> 1"');

                $type = 'oneToOne';
                $relationName = lcfirst($targetEntity);
                if (!isset($values[$type])) {
                    $values[$type] = [];
                }
                $values[$type][$relationName] = array_merge([
                    'targetEntity' => $targetEntity,
                    'mappedBy' => lcfirst($this->getRelatedVarName($mappedBy, $related)),
                ], $this->getJoins($local));
            }
            if (!is_null($cacheMode = $this->getFormatter()->getCacheOption($local->parseComment('cache')))) {
                $values[$type][$relationName]['cache']['usage'] = $cacheMode;
            }
        }

        // N <=> ? references
        foreach ($this->getAllForeignKeys() as $foreign) {
            if ($this->isForeignKeyIgnored($foreign)) {
                continue;
            }
            $targetEntity = $foreign->getReferencedTable()->getModelName();
            $targetEntityFQCN = $foreign->getReferencedTable()->getModelNameAsFQCN($foreign->getOwningTable()->getEntityNamespace());
            $inversedBy = $foreign->getOwningTable()->getModelName();
            $related = $this->getRelatedName($foreign);

            $this->getDocument()->addLog(sprintf('  Writing N <=> ? relation "%s"', $targetEntity));

            if ($foreign->isManyToOne()) {
                $this->getDocument()->addLog('  Relation considered as "N <=> 1"');

                $type = 'manyToOne';
                $relationName = lcfirst($this->getRelatedVarName($targetEntity, $related));
                if (!isset($values[$type])) {
                    $values[$type] = [];
                }
                $values[$type][$relationName] = array_merge([
                    'targetEntity' => $targetEntityFQCN,
                    'inversedBy' => lcfirst($this->getRelatedVarName($inversedBy, $related, true)),
                ], $this->getJoins($foreign, false));
            } else {
                $this->getDocument()->addLog('  Relation considered as "1 <=> 1"');

                $type = 'oneToOne';
                $relationName = lcfirst($targetEntity);
                if (!isset($values[$type])) {
                    $values[$type] = [];
                }
                $values[$type][$relationName] = array_merge([
                    'targetEntity' => $targetEntityFQCN,
                    'inversedBy' => $foreign->isUnidirectional() ? null : lcfirst($this->getRelatedVarName($inversedBy, $related)),
                ], $this->getJoins($foreign, false));
            }
            if (!is_null($cacheMode = $this->getFormatter()->getCacheOption($foreign->parseComment('cache')))) {
                $values[$type][$relationName]['cache']['usage'] = $cacheMode;
            }
        }

        return $this;
    }

    protected function getM2MRelationsAsYAML(&$values)
    {
        // many to many relations
        foreach ($this->getTableM2MRelations() as $relation) {
            $fk1 = $relation['reference'];
            $isOwningSide = $this->getFormatter()->isOwningSide($relation, $fk2);
            $mappings = [
                'targetEntity' => $relation['refTable']->getModelNameAsFQCN($this->getEntityNamespace()),
                'mappedBy' => null,
                'inversedBy' => lcfirst($this->getPluralModelName()),
                'cascade' => $this->getFormatter()->getCascadeOption($fk1->parseComment('cascade')),
                'fetch' => $this->getFormatter()->getFetchOption($fk1->parseComment('fetch')),
            ];

            $relationName = $this->pluralize($relation['refTable']->getRawTableName());
            /** @var \MwbExporter\Configuration\Language $lang */
            $lang = $this->getConfig(LanguageConfiguration::class);
            if ($inflector = $lang->getInflector()) {
                $relationName = $inflector->camelize($relationName);
            }

            // if this is the owning side, also output the JoinTable Annotation
            // otherwise use "mappedBy" feature
            if ($isOwningSide) {
                if ($fk1->isUnidirectional()) {
                    unset($mappings['inversedBy']);
                }

                $type = 'manyToMany';
                if (!isset($values[$type])) {
                    $values[$type] = [];
                }
                $values[$type][$relationName] = array_merge($mappings, [
                    'joinTable' => [
                        'name' => $fk1->getOwningTable()->getRawTableName(),
                        'joinColumns' => $this->convertJoinColumns($this->getJoins($fk1, false)),
                        'inverseJoinColumns' => $this->convertJoinColumns($this->getJoins($fk2, false)),
                    ],
                ]);
                if (!is_null($cacheMode = $this->getFormatter()->getCacheOption($fk1->parseComment('cache')))) {
                    $values[$type][$relationName]['cache']['usage'] = $cacheMode;
                }
            } else {
                if ($fk2->isUnidirectional()) {
                    continue;
                }
                $mappings['mappedBy'] = $mappings['inversedBy'];
                $mappings['inversedBy'] = null;

                $type = 'manyToMany';
                if (!isset($values[$type])) {
                    $values[$type] = [];
                }
                $values[$type][$relationName] = $mappings;
                if (!is_null($cacheMode = $this->getFormatter()->getCacheOption($fk2->parseComment('cache')))) {
                    $values[$type][$relationName]['cache']['usage'] = $cacheMode;
                }
            }
        }

        return $this;
    }

    protected function convertJoinColumns($joins = [])
    {
        $result = [];
        foreach ($joins as $join) {
            if (!isset($join['name'])) {
                continue;
            }
            $key = $join['name'];
            unset($join['name']);
            $result[$key] = $join;
        }

        return $result;
    }

    /**
     * Get foreign key join descriptor.
     *
     * @param \MwbExporter\Model\ForeignKey $fkey  Foreign key
     * @param string $owningSide  Is join for owning side or vice versa
     * @return array
     */
    protected function getJoins(ForeignKey $fkey, $owningSide = true)
    {
        $joins = [];
        $lcols = $owningSide ? $fkey->getForeigns() : $fkey->getLocals();
        $fcols = $owningSide ? $fkey->getLocals() : $fkey->getForeigns();
        $onDelete = $this->getFormatter()->getDeleteRule($fkey->getParameters()->get('deleteRule'));
        for ($i = 0; $i < count($lcols); $i++) {
            $joins[] = [
                'name' => $lcols[$i]->getColumnName(),
                'referencedColumnName' => $fcols[$i]->getColumnName(),
                'nullable' => $lcols[$i]->getNullableValue(),
                'onDelete' => $onDelete,
            ];
        }

        return count($joins) > 1 ? ['joinColumns' => $this->convertJoinColumns($joins)] : ['joinColumn' => $joins[0]];
    }

    /**
     * (non-PHPdoc)
     * @see \MwbExporter\Model\Base::getVars()
     */
    protected function getVars()
    {
        return array_merge(parent::getVars(), ['%entity-fqcn%' => str_replace('\\', '.', $this->getModelNameAsFQCN())]);
    }
}
