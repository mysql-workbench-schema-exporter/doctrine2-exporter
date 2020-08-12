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

use MwbExporter\Formatter\Doctrine2\Model\Table as BaseTable;
use MwbExporter\Formatter\Doctrine2\Annotation\Formatter;
use MwbExporter\Model\ForeignKey;
use MwbExporter\Object\Annotation;
use MwbExporter\Writer\WriterInterface;
use MwbExporter\Helper\Comment;
use MwbExporter\Helper\ReservedWords;

class Table extends BaseTable
{
    protected $ormPrefix = null;
    protected $collectionClass = 'Doctrine\Common\Collections\ArrayCollection';
    protected $collectionInterface = 'Doctrine\Common\Collections\Collection';

    /**
     * Get the array collection class name.
     *
     * @param bool $useFQCN return full qualified class name
     * @return string
     */
    public function getCollectionClass($useFQCN = true)
    {
        $class = $this->collectionClass;
        if (!$useFQCN && count($array = explode('\\', $class))) {
            $class = array_pop($array);
        }

        return $class;
    }

    /**
     * Get collection interface class name.
     *
     * @param bool $absolute Use absolute class name
     * @return string
     */
    public function getCollectionInterface($absolute = true)
    {
        return ($absolute ? '\\' : '').$this->collectionInterface;
    }

    /**
     * Get annotation prefix.
     *
     * @param string $annotation Annotation type
     * @return string
     */
    public function addPrefix($annotation = null)
    {
        if (null === $this->ormPrefix) {
            $this->ormPrefix = '@'.$this->getConfig()->get(Formatter::CFG_ANNOTATION_PREFIX);
        }

        return $this->ormPrefix.($annotation ? $annotation : '');
    }

    /**
     * Quote identifier if necessary.
     *
     * @param string $value  The identifier to quote
     * @return string
     */
    public function quoteIdentifier($value)
    {
        $quote = false;
        switch ($this->getConfig()->get(Formatter::CFG_QUOTE_IDENTIFIER_STRATEGY)) {
            case Formatter::QUOTE_IDENTIFIER_AUTO:
                $quote = ReservedWords::isReserved($value);
                break;

            case Formatter::QUOTE_IDENTIFIER_ALWAYS:
                $quote = true;
                break;
        }

        return $quote ? '`'.$value.'`' : $value;
    }

    /**
     * Get annotation object.
     *
     * @param string $annotation  The annotation name
     * @param mixed  $content     The annotation content
     * @param array  $options     The annotation options
     * @return \MwbExporter\Object\Annotation
     */
    public function getAnnotation($annotation, $content = null, $options = array())
    {
        return new Annotation($this->addPrefix($annotation), $content, $options);
    }

    /**
     * Get indexes annotation.
     *
     * @param string $type Index annotation type, Index or UniqueConstraint
     * @return array|null
     */
    protected function getIndexesAnnotation($type = 'Index')
    {
        $indices = array();
        foreach ($this->getTableIndices() as $index) {
            switch (true) {
                case $type === 'Index' && $index->isIndex():
                case $type === 'UniqueConstraint' && $index->isUnique():
                    $columns = array();
                    foreach ($index->getColumns() as $column) {
                        $columns[] = $this->quoteIdentifier($column->getColumnName());
                    }
                    $indices[] = $this->getAnnotation($type, array('name' => $index->getName(), 'columns' => $columns));
                    break;

                default:
                    break;
            }
        }

        return count($indices) ? $indices : null;
    }

    /**
     * Get join annotation.
     *
     * @param string $joinType    Join type
     * @param string $entity      Entity name
     * @param string $mappedBy    Column mapping
     * @param string $inversedBy  Reverse column mapping
     * @return \MwbExporter\Object\Annotation
     */
    public function getJoinAnnotation($joinType, $entity, $mappedBy = null, $inversedBy = null)
    {
        return $this->getAnnotation($joinType, array('targetEntity' => $entity, 'mappedBy' => $mappedBy, 'inversedBy' => $inversedBy));
    }

    /**
     * Get foreign key join annotation. If foreign key is composite
     * JoinColumns returned, otherwise JoinColumn returned.
     *
     * @param \MwbExporter\Model\ForeignKey $fkey  Foreign key
     * @param boolean $owningSide  Is join for owning side or vice versa
     * @return \MwbExporter\Object\Annotation
     */
    protected function getJoins(ForeignKey $fkey, $owningSide = true)
    {
        $joins = array();
        $lcols = $owningSide ? $fkey->getForeigns() : $fkey->getLocals();
        $fcols = $owningSide ? $fkey->getLocals() : $fkey->getForeigns();
        $onDelete = $this->getFormatter()->getDeleteRule($fkey->getParameters()->get('deleteRule'));
        for ($i = 0; $i < count($lcols); $i++) {
            $joins[] = $this->getAnnotation('JoinColumn', array(
                'name'                  => $this->quoteIdentifier($lcols[$i]->getColumnName()),
                'referencedColumnName'  => $this->quoteIdentifier($fcols[$i]->getColumnName()),
                'nullable'              => $lcols[$i]->getNullableValue(true),
                'onDelete'              => $onDelete,
            ));
        }

        return count($joins) > 1 ? $this->getAnnotation('JoinColumns', array($joins), array('multiline' => true, 'wrapper' => ' * %s')) : $joins[0];
    }

    public function writeTable(WriterInterface $writer)
    {
        switch (true) {
            case $this->isExternal():
                return self::WRITE_EXTERNAL;

            case $this->getConfig()->get(Formatter::CFG_SKIP_M2M_TABLES) && $this->isManyToMany():
                return self::WRITE_M2M;

            default:
                $this->writeEntity($writer);
                return self::WRITE_OK;
        }
    }

    protected function writeEntity(WriterInterface $writer)
    {
        $this->getDocument()->addLog(sprintf('Writing table "%s"', $this->getModelName()));

        if ($repositoryNamespace = $this->getConfig()->get(Formatter::CFG_REPOSITORY_NAMESPACE)) {
            $repositoryNamespace .= '\\';
        }
        $skipGetterAndSetter = $this->getConfig()->get(Formatter::CFG_SKIP_GETTER_SETTER);
        $serializableEntity  = $this->getConfig()->get(Formatter::CFG_GENERATE_ENTITY_SERIALIZATION);
        $extendableEntity    = $this->getConfig()->get(Formatter::CFG_GENERATE_EXTENDABLE_ENTITY);
        $extendableEntityHasDiscriminator = $this->getConfig()->get(Formatter::CFG_EXTENDABLE_ENTITY_HAS_DISCRIMINATOR);
        $useBehavioralExtensions = $this->getConfig()->get(Formatter::CFG_USE_BEHAVIORAL_EXTENSIONS);
        $lifecycleCallbacks  = $this->getLifecycleCallbacks();
        $cacheMode           = $this->getEntityCacheMode();

        $namespace = $this->getEntityNamespace().($extendableEntity ? '\\Base' : '');

        $extendsClass = $this->getClassToExtend();
        $implementsInterface = $this->getInterfaceToImplement();

        $hasDeletableBehaviour = false;
        $hasTimestampableBehaviour = false;
        if ($useBehavioralExtensions) {
            foreach ($this->getColumns() as $column) {
                if ($column->getColumnName() === 'deleted_at') {
                    $hasDeletableBehaviour = true;
                } elseif ($column->getColumnName() === 'created_at') {
                    $hasTimestampableBehaviour = true;
                } elseif ($column->getColumnName() === 'updated_at') {
                    $hasTimestampableBehaviour = true;
                }
            }
        }

        $comment = $this->getComment();
        $writer
            ->open($this->getClassFileName($extendableEntity ? true : false))
            ->write('<?php')
            ->write('')
            ->writeCallback(function(WriterInterface $writer, Table $_this = null) {
                if ($_this->getConfig()->get(Formatter::CFG_ADD_COMMENT)) {
                    $writer
                        ->write($_this->getFormatter()->getComment(Comment::FORMAT_PHP))
                        ->write('')
                    ;
                }
            })
            ->write('namespace %s;', $namespace)
            ->write('')
            ->writeIf($useBehavioralExtensions &&
                (strstr($this->getClassName($extendableEntity), 'Img') || $hasDeletableBehaviour || $hasTimestampableBehaviour),
                'use Gedmo\Mapping\Annotation as Gedmo;')
            ->writeCallback(function(WriterInterface $writer, Table $_this = null) {
                $_this->writeUsedClasses($writer);
            })
            ->write('/**')
            ->write(' * '.$this->getNamespace(null, false))
            ->write(' *')
            ->writeIf($comment, $comment)
            ->writeIf($extendableEntity, ' * '.$this->addPrefix('MappedSuperclass'))
            ->writeIf($hasDeletableBehaviour,
                    ' * @Gedmo\SoftDeleteable(fieldName="deleted_at", timeAware=false, hardDelete=false)')
            ->writeIf(!$extendableEntity,
                    ' * '.$this->getAnnotation('Entity', array('repositoryClass' => $this->getConfig()->get(Formatter::CFG_AUTOMATIC_REPOSITORY) ? $repositoryNamespace.$this->getModelName().'Repository' : null)))
            ->writeIf($cacheMode, ' * '.$this->getAnnotation('Cache', array($cacheMode)))
            ->write(' * '.$this->getAnnotation('Table', array('name' => $this->quoteIdentifier($this->getRawTableName()), 'indexes' => $this->getIndexesAnnotation('Index'), 'uniqueConstraints' => $this->getIndexesAnnotation('UniqueConstraint'))))
            ->writeIf($extendableEntityHasDiscriminator,
                    ' * '.$this->getAnnotation('InheritanceType', array('SINGLE_TABLE')))
            ->writeIf($extendableEntityHasDiscriminator,
                    ' * '.$this->getAnnotation('DiscriminatorColumn', $this->getInheritanceDiscriminatorColumn()))
            ->writeIf($extendableEntityHasDiscriminator,
                    ' * '.$this->getAnnotation('DiscriminatorMap', array($this->getInheritanceDiscriminatorMap())))
            ->writeIf($lifecycleCallbacks, ' * '.$this->addPrefix('HasLifecycleCallbacks'))
            ->writeIf($useBehavioralExtensions && strstr($this->getClassName($extendableEntity), 'Img'),
                    ' * @Gedmo\Uploadable(path="./public/upload/' . $this->getClassName($extendableEntity) . '", filenameGenerator="SHA1", allowOverwrite=true, appendNumber=true)')
            ->write(' */')
            ->write('class '.$this->getClassName($extendableEntity).$extendsClass.$implementsInterface)
            ->write('{')
            ->indent()
                ->writeCallback(function(WriterInterface $writer, Table $_this = null) use ($skipGetterAndSetter, $serializableEntity, $lifecycleCallbacks) {
                    $_this->writePreClassHandler($writer);
                    $_this->writeVars($writer);
                    $_this->writeConstructor($writer);
                    if (!$skipGetterAndSetter) {
                        $_this->writeGetterAndSetter($writer);
                    }
                    $_this->writePostClassHandler($writer);
                    foreach ($lifecycleCallbacks as $callback => $handlers) {
                        foreach ($handlers as $handler) {
                            $writer
                                ->write('/**')
                                ->write(' * '.$this->addPrefix(ucfirst($callback)))
                                ->write(' */')
                                ->write('public function %s()', $handler)
                                ->write('{')
                                ->write('}')
                                ->write('')
                            ;
                        }
                    }
                    if ($serializableEntity) {
                        $_this->writeSerialization($writer);
                    }
                })
            ->outdent()
            ->write('}')
            ->close()
        ;

        $namespace = $this->getEntityNamespace();

        if ($extendableEntity && !$writer->getStorage()->hasFile($this->getClassFileName())) {
            $writer
                ->open($this->getClassFileName())
                ->write('<?php')
                ->write('')
                ->writeCallback(function(WriterInterface $writer, Table $_this = null) {
                    if ($_this->getConfig()->get(Formatter::CFG_ADD_COMMENT)) {
                        $writer
                            ->write($_this->getFormatter()->getComment(Comment::FORMAT_PHP))
                            ->write('')
                        ;
                    }
                })
                ->write('namespace %s;', $namespace)
                ->write('')
                ->write('use %s\\%s;', $namespace, $this->getClassName(true, 'Base\\'))
                ->write('')
                ->writeCallback(function(WriterInterface $writer, Table $_this = null) {
                    $_this->writeExtendedUsedClasses($writer);
                })
                ->write('/**')
                ->write(' * '.$this->getNamespace(null, false))
                ->write(' *')
                ->writeIf($comment, $comment)
                ->write(' * '.$this->getAnnotation('Entity', array('repositoryClass' => $this->getConfig()->get(Formatter::CFG_AUTOMATIC_REPOSITORY) ? $repositoryNamespace.$this->getModelName().'Repository' : null)))
                ->write(' * '.$this->getAnnotation('Table', array('name' => $this->quoteIdentifier($this->getRawTableName()))))
                ->write(' */')
                ->write('class %s extends %s', $this->getClassName(), $this->getClassName(true))
                ->write('{')
                ->write('}')
                ->close()
            ;
        }
    }

    /**
     * Get the generated class name.
     *
     * @param bool $base
     * @return string
     */
    protected function getClassFileName($base = false)
    {
        return ($base ? $this->getTableFileName(null, array('%entity%' => 'Base'.DIRECTORY_SEPARATOR.'Base'.$this->getModelName())) : $this->getTableFileName());
    }

    /**
     * Get the generated class name.
     *
     * @param bool $base
     * @return string
     */
    protected function getClassName($base = false, $prefix = '')
    {
        return $prefix.($base ? 'Base' : '').$this->getModelName();
    }

    /**
     * Get the class name to implement.
     *
     * @return string
     */
    protected function getClassImplementations()
    {
    }

    /**
     * Get the class name to extend
     *
     * @return string
     */
    protected function getClassToExtend()
    {
        $class = $this->getConfig()->get(Formatter::CFG_EXTENDS_CLASS);
        if (empty($class)) {
            return '';
        }

        return " extends $class";
    }

    /**
     * Get the class name to implement
     *
     * @return string
     */
    protected function getInterfaceToImplement()
    {
        $interface = $this->getClassImplementations();
        if (empty($interface)) {
            return '';
        }

        return " implements $interface";
    }

    /**
     * Get the use class for ORM if applicable.
     *
     * @return string
     */
    protected function getOrmUse()
    {
        if ('@ORM\\' === $this->addPrefix()) {
            return 'Doctrine\ORM\Mapping as ORM';
        }
    }

    /**
     * Get used classes.
     *
     * @return array
     */
    protected function getUsedClasses()
    {
        $uses = array();
        if (count($this->getTableM2MRelations()) || count($this->getAllLocalForeignKeys())) {
            $uses[] = $this->getCollectionClass();
        }
        if ($orm = $this->getOrmUse()) {
            $uses[] = $orm;
        }

        return $uses;
    }

    protected function getInheritanceDiscriminatorColumn()
    {
        $result = array();
        if ($column = trim($this->parseComment('discriminator'))) {
            $result['name'] = $column;
            foreach ($this->getColumns() as $col) {
                if ($column == $col->getColumnName()) {
                    $result['type'] = $this->getFormatter()->getDatatypeConverter()->getDataType($col->getColumnType());
                    break;
                }
            }
        } else {
            $result['name'] = 'discr';
            $result['type'] = 'string';
        }

        return $result;
    }

    protected function getInheritanceDiscriminatorMap()
    {
        return array('base' => $this->getNamespace($this->getClassName(true, 'Base\\')), 'extended' => $this->getNamespace());
    }

    public function writeUsedClasses(WriterInterface $writer)
    {
        $this->writeUses($writer, $this->getUsedClasses());

        return $this;
    }

    public function writeExtendedUsedClasses(WriterInterface $writer)
    {
        $uses = array();
        if ($orm = $this->getOrmUse()) {
            $uses[] = $orm;
        }
        $uses[] = sprintf('%s\%s', $this->getEntityNamespace(), $this->getClassName(true));
        $this->writeUses($writer, $uses);

        return $this;
    }

    protected function writeUses(WriterInterface $writer, $uses = array())
    {
        if (count($uses)) {
            foreach ($uses as $use) {
                $writer->write('use %s;', $use);
            }
            $writer->write('');
        }

        return $this;
    }

    /**
     * Write pre class handler.
     *
     * @param \MwbExporter\Writer\WriterInterface $writer
     * @return \MwbExporter\Formatter\Doctrine2\Annotation\Model\Table
     */
    public function writePreClassHandler(WriterInterface $writer)
    {
        return $this;
    }

    public function writeVars(WriterInterface $writer)
    {
        $this->writeColumnsVar($writer);
        $this->writeRelationsVar($writer);
        $this->writeManyToManyVar($writer);

        return $this;
    }

    protected function writeColumnsVar(WriterInterface $writer)
    {
        foreach ($this->getColumns() as $column) {
            $column->writeVar($writer);
        }
    }

    protected function writeRelationsVar(WriterInterface $writer)
    {
        // 1 <=> N references
        foreach ($this->getAllLocalForeignKeys() as $local) {
            if ($this->isLocalForeignKeyIgnored($local)) {
                $this->getDocument()->addLog(sprintf('  Local relation "%s" was ignored', $local->getOwningTable()->getModelName()));
                continue;
            }

            $targetEntity = $local->getOwningTable()->getName();
            $targetEntityFQCN = $local->getOwningTable()->getModelNameAsFQCN();
            $mappedBy = $local->getReferencedTable()->getName();
            $related = $local->getForeignM2MRelatedName();
            $cacheMode = $this->getFormatter()->getCacheOption($local->parseComment('cache'));

            $this->getDocument()->addLog(sprintf('  Writing 1 <=> ? relation "%s"', $targetEntity));

            $annotationOptions = array(
                'targetEntity' => $targetEntityFQCN,
                'mappedBy' => $this->getNaming($local->getOwningTable()->getRelatedVarName($mappedBy, $related, false, $local)),
                'cascade' => $this->getFormatter()->getCascadeOption($local->parseComment('cascade')),
                'fetch' => $this->getFormatter()->getFetchOption($local->parseComment('fetch')),
                'orphanRemoval' => $this->getFormatter()->getBooleanOption($local->parseComment('orphanRemoval')),
            );

            if ($local->isManyToOne()) {
                $this->getDocument()->addLog('  Relation considered as "1 <=> N"');

                $variableName = $this->getNaming($this->getRelatedVarName($local->getOwningTable()->getModelName(), $related, true, $local));

                $writer
                    ->write('/**')
                    ->writeIf($cacheMode, ' * '.$this->getAnnotation('Cache', array($cacheMode)))
                    ->write(' * '.$this->getAnnotation('OneToMany', $annotationOptions))
                    ->write(' * '.$this->getJoins($local))
                    ->writeCallback(function(WriterInterface $writer, Table $_this = null) use ($local) {
                        if (count($orders = $_this->getFormatter()->getOrderOption($local->parseComment('order')))) {
                            $writer
                                ->write(' * '.$_this->getAnnotation('OrderBy', array($orders)))
                            ;
                        }
                    })
                    ->write(' */')
                    ->write('protected $'.$variableName.';')
                    ->write('')
                ;
            } else {
                $this->getDocument()->addLog('  Relation considered as "1 <=> 1"');

                $writer
                    ->write('/**')
                    ->writeIf($cacheMode, ' * '.$this->getAnnotation('Cache', array($cacheMode)))
                    ->write(' * '.$this->getAnnotation('OneToOne', $annotationOptions))
                    ->write(' */')
                    ->write('protected $'.$this->getNaming($targetEntity).';')
                    ->write('')
                ;
            }
        }

        // N <=> 1 references
        foreach ($this->getAllForeignKeys() as $foreign) {
            if ($this->isForeignKeyIgnored($foreign)) {
                $this->getDocument()->addLog(sprintf('  Foreign relation "%s" was ignored', $foreign->getOwningTable()->getModelName()));
                continue;
            }

            $targetEntity = $foreign->getReferencedTable()->getName();
            $targetEntityFQCN = $foreign->getReferencedTable()->getModelNameAsFQCN();
            $inversedBy = $foreign->getOwningTable()->getName();
            $related = $this->getRelatedName($foreign);

            $this->getDocument()->addLog(sprintf('  Writing N <=> ? relation "%s"', $targetEntity));

            $annotationOptions = array(
                'targetEntity' => $targetEntityFQCN,
                'inversedBy' => $foreign->isUnidirectional() ? null : $this->getNaming($this->getRelatedVarName($inversedBy, $related, true, $foreign)),
                'cascade' => $this->getFormatter()->getCascadeOption($foreign->parseComment('cascade')),
                'fetch' => $this->getFormatter()->getFetchOption($foreign->parseComment('fetch')),
            );
            $cacheMode = $this->getFormatter()->getCacheOption($foreign->parseComment('cache'));

            if ($foreign->isManyToOne()) {
                $this->getDocument()->addLog('  Relation considered as "N <=> 1"');

                $variableName = $this->getNaming($this->getRelatedVarName($foreign->getReferencedTable()->getModelName(), $related, false, $foreign));

                $writer
                    ->write('/**')
                    ->writeIf($cacheMode, ' * '.$this->getAnnotation('Cache', array($cacheMode)))
                    ->write(' * '.$this->getAnnotation('ManyToOne', $annotationOptions))
                    ->write(' * '.$this->getJoins($foreign, false))
                    ->write(' */')
                    ->write('protected $'.$variableName.';')
                    ->write('')
                ;
            } else {
                $this->getDocument()->addLog('  Relation considered as "1 <=> 1"');

                if (null !== $annotationOptions['inversedBy']) {
                    $annotationOptions['inversedBy'] = $this->getNaming($this->getRelatedVarName($inversedBy, $related, false, $foreign));
                }
                $annotationOptions['cascade'] = $this->getFormatter()->getCascadeOption($foreign->parseComment('cascade'));

                $writer
                    ->write('/**')
                    ->writeIf($cacheMode, ' * '.$this->getAnnotation('Cache', array($cacheMode)))
                    ->write(' * '.$this->getAnnotation('OneToOne', $annotationOptions))
                    ->write(' * '.$this->getJoins($foreign, false))
                    ->write(' */')
                    ->write('protected $'.$this->getNaming($targetEntity).';')
                    ->write('')
                ;
            }
        }

        return $this;
    }

    protected function writeManyToManyVar(WriterInterface $writer)
    {
        foreach ($this->getTableM2MRelations() as $relation) {
            $this->getDocument()->addLog(sprintf('  Writing setter/getter for N <=> N "%s"', $relation['refTable']->getModelName()));

            $fk1 = $relation['reference'];
            $fk2 = null;

            $isOwningSide = $this->getFormatter()->isOwningSide($relation, $fk2);
            $annotationOptions = array(
                'targetEntity' => $relation['refTable']->getModelNameAsFQCN(),
                'mappedBy' => null,
                'inversedBy' => $this->getNaming($this->getRelatedVarName($this->getModelName(), null, true, $fk1)),
                'cascade' => $this->getFormatter()->getCascadeOption($fk1->parseComment('cascade')),
                'fetch' => $this->getFormatter()->getFetchOption($fk1->parseComment('fetch')),
            );
            $cacheMode = $this->getFormatter()->getCacheOption($fk1->parseComment('cache'));

            // if this is the owning side, also output the JoinTable Annotation
            // otherwise use "mappedBy" feature
            if ($isOwningSide) {
                $this->getDocument()->addLog(sprintf('  Applying setter/getter for N <=> N "%s"', "owner"));

                if ($fk1->isUnidirectional()) {
                    unset($annotationOptions['inversedBy']);
                }

                $writer
                    ->write('/**')
                    ->writeIf($cacheMode, ' * '.$this->getAnnotation('Cache', array($cacheMode)))
                    ->write(' * '.$this->getAnnotation('ManyToMany', $annotationOptions))
                    ->write(' * '.$this->getAnnotation('JoinTable',
                        array(
                            'name'               => $this->quoteIdentifier($relation['reference']->getOwningTable()->getRawTableName()),
                            'joinColumns'        => array($this->getJoins($fk1, false)),
                            'inverseJoinColumns' => array($this->getJoins($fk2, false)),
                        ), array('multiline' => true, 'wrapper' => ' * %s')))
                    ->writeCallback(function(WriterInterface $writer, Table $_this = null) use ($fk2) {
                        if (count($orders = $_this->getFormatter()->getOrderOption($fk2->parseComment('order')))) {
                            $writer
                                ->write(' * '.$_this->getAnnotation('OrderBy', array($orders)))
                            ;
                        }
                    })
                    ->write(' */')
                ;
            } else {
                $this->getDocument()->addLog(sprintf('  Applying setter/getter for N <=> N "%s"', "inverse"));

                if ($fk2->isUnidirectional()) {
                    continue;
                }

                $annotationOptions['mappedBy'] = $annotationOptions['inversedBy'];
                $annotationOptions['inversedBy'] = null;
                $writer
                    ->write('/**')
                    ->writeIf($cacheMode, ' * '.$this->getAnnotation('Cache', array($cacheMode)))
                    ->write(' * '.$this->getAnnotation('ManyToMany', $annotationOptions))
                    ->write(' */')
                ;
            }

            $variableName = $this->getNaming($this->getRelatedVarName($relation['refTable']->getModelName(), null, true, $fk1));
            $writer
                ->write('protected $'.$variableName.';')
                ->write('')
            ;
        }

        return $this;
    }

    public function writeConstructor(WriterInterface $writer)
    {
        $writer
            ->write('public function __construct()')
            ->write('{')
            ->indent()
                ->writeCallback(function(WriterInterface $writer, Table $_this = null) {
                    $_this->writeCurrentTimestampConstructor($writer);
                    $_this->writeRelationsConstructor($writer);
                    $_this->writeManyToManyConstructor($writer);
                })
            ->outdent()
            ->write('}')
            ->write('')
        ;

        return $this;
    }

    public function writeCurrentTimestampConstructor(WriterInterface $writer)
    {
        foreach ($this->getColumns() as $column) {
            if ('CURRENT_TIMESTAMP' === $column->getDefaultValue()) {
                $writer->write('$this->%s = new \DateTime(\'now\');', $column->getColumnName(false));
            }
        }
    }

    public function writeRelationsConstructor(WriterInterface $writer)
    {
        foreach ($this->getAllLocalForeignKeys() as $local) {
            if ($this->isLocalForeignKeyIgnored($local)) {
                continue;
            }
            $this->getDocument()->addLog(sprintf('  Writing N <=> 1 constructor "%s"', $local->getOwningTable()->getModelName()));

            $related = $local->getForeignM2MRelatedName();
            $variableName = $this->getNaming($this->getRelatedVarName($local->getOwningTable()->getModelName(), $related, true, $local));
            $writer->write('$this->%s = new %s();', $variableName, $this->getCollectionClass(false));
        }
    }

    public function writeManyToManyConstructor(WriterInterface $writer)
    {
        foreach ($this->getTableM2MRelations() as $relation) {

            $fk1 = $relation['reference'];

            $this->getDocument()->addLog(sprintf('  Writing M2M constructor "%s"', $relation['refTable']->getModelName()));
            $variableName = $this->getNaming($this->getRelatedVarName($relation['refTable']->getModelName(), null, true, $fk1));
            $writer->write('$this->%s = new %s();', $variableName, $this->getCollectionClass(false));


        }
    }

    public function writeGetterAndSetter(WriterInterface $writer)
    {
        $this->writeColumnsGetterAndSetter($writer);
        $this->writeRelationsGetterAndSetter($writer);
        $this->writeManyToManyGetterAndSetter($writer);

        return $this;
    }

    protected function writeColumnsGetterAndSetter(WriterInterface $writer)
    {
        foreach ($this->getColumns() as $column) {
            $column->writeGetterAndSetter($writer);
        }
    }

    protected function writeRelationsGetterAndSetter(WriterInterface $writer)
    {
        // N <=> 1 references
        foreach ($this->getAllLocalForeignKeys() as $local) {
            if ($this->isLocalForeignKeyIgnored($local)) {
                continue;
            }

            $this->getDocument()->addLog(sprintf('  Writing setter/getter for N <=> ? "%s"', $local->getParameters()->get('name')));

            if ($local->isManyToOne()) {
                // N <=> 1 references
                $this->getDocument()->addLog(sprintf('  Applying setter/getter for "%s"', 'N <=> 1'));

                $related = $local->getForeignM2MRelatedName();
                $related_text = $local->getForeignM2MRelatedName(false);
                $nameSingular = $this->getNaming($this->getRelatedVarName($local->getOwningTable()->getModelName(), $related, false, $local, true));
                $namePlural = $this->getNaming($this->getRelatedVarName($local->getOwningTable()->getModelName(), $related, true, $local));

                $variableNameSingular = $nameSingular;
                $variableNamePlural = $namePlural;

                $typehints = array(
                    'add_phpdoc_arg' => $this->typehint($local->getOwningTable()->getNamespace(), false),
                    'add_phpdoc_return' => $this->typehint($this->getNamespace(), false),
                    'add_arg' => $this->paramTypehint($local->getOwningTable()->getNamespace(), false),
                    'add_return' => $this->returnTypehint(null, false),

                    'remove_phpdoc_arg' => $this->typehint($local->getOwningTable()->getNamespace(), false),
                    'remove_phpdoc_return' => $this->typehint($this->getNamespace(), false),
                    'remove_arg' => $this->paramTypehint($local->getOwningTable()->getNamespace(), false),
                    'remove_return' => $this->returnTypehint(null, false),

                    'get_phpdoc' => $this->typehint($this->getCollectionInterface(), false),
                    'get_return' => $this->returnTypehint(null, false),
                );

                $writer
                    // setter
                    ->write('/**')
                    ->write(' * Add '.trim($local->getOwningTable()->getModelName().' entity '.$related_text). ' to collection (one to many).')
                    ->write(' *')
                    ->write(' * @param '.$typehints['add_phpdoc_arg'].' $'.$variableNameSingular)
                    ->write(' *')
                    ->write(' * @return '.$typehints['add_phpdoc_return'])
                    ->write(' */')
                    ->write('public function add'.ucfirst($nameSingular).'('.$typehints['add_arg'].'$'.$variableNameSingular.')'.$typehints['add_return'])
                    ->write('{')
                    ->indent()
                        ->write('$this->'.$variableNamePlural.'[] = $'.$variableNameSingular.';')
                        ->write('')
                        ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                    // remover
                    ->write('/**')
                    ->write(' * Remove '.trim($local->getOwningTable()->getModelName().' entity '.$related_text). ' from collection (one to many).')
                    ->write(' *')
                    ->write(' * @param '.$typehints['remove_phpdoc_arg'].' $'.$variableNameSingular)
                    ->write(' *')
                    ->write(' * @return '.$typehints['remove_phpdoc_return'])
                    ->write(' */')
                    ->write('public function remove'.ucfirst($nameSingular).'('.$typehints['remove_arg'].'$'.$variableNameSingular.')'.$typehints['remove_return'])
                    ->write('{')
                    ->indent()
                        ->write('$this->'.$variableNamePlural.'->removeElement($'.$variableNameSingular.');')
                        ->write('')
                        ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                    // getter
                    ->write('/**')
                    ->write(' * Get '.trim($local->getOwningTable()->getModelName().' entity '.$related_text).' collection (one to many).')
                    ->write(' *')
                    ->write(' * @return '.$typehints['get_phpdoc'])
                    ->write(' */')
                    ->write('public function get'.ucfirst($namePlural).'()'.$typehints['get_return'])
                    ->write('{')
                    ->indent()
                        ->write('return $this->'.$variableNamePlural.';')
                    ->outdent()
                    ->write('}')
                    ->write('')
                ;
            } else {
                // 1 <=> 1 references
                $this->getDocument()->addLog(sprintf('  Applying setter/getter for "%s"', '1 <=> 1'));

                $nullable = true;
                foreach ($local->getLocals() as $lc) {
                    $nullable = $nullable && $lc->isNotNull();
                }

                $typehints = array(
                    'set_phpdoc_arg' => $this->typehint($local->getOwningTable()->getNamespace(), $nullable),
                    'set_phpdoc_return' => $this->typehint($this->getNamespace(), false),
                    'set_arg' => $this->paramTypehint($local->getOwningTable()->getNamespace(), $nullable),
                    'set_return' => $this->returnTypehint(null, false),

                    'get_phpdoc' => $this->typehint($local->getOwningTable()->getNamespace(), $nullable),
                    'get_return' => $this->returnTypehint($local->getOwningTable()->getNamespace(), true),
                );

                $writer
                    // setter
                    ->write('/**')
                    ->write(' * Set '.$local->getOwningTable()->getModelName().' entity (one to one).')
                    ->write(' *')
                    ->write(' * @param '.$typehints['set_phpdoc_arg'].' $'.$this->getNaming($local->getOwningTable()->getName()))
                    ->write(' *')
                    ->write(' * @return '.$typehints['set_phpdoc_return'])
                    ->write(' */')
                    ->write('public function set'.$local->getOwningTable()->getModelName().'('.$typehints['set_arg'].'$'.$this->getNaming($local->getOwningTable()->getName()).')'.$typehints['set_return'])
                    ->write('{')
                    ->indent()
                        ->writeIf(!$local->isUnidirectional(), '$'.$this->getNaming($local->getOwningTable()->getName()).'->set'.$local->getReferencedTable()->getModelName().'($this);')
                        ->write('$this->'.$this->getNaming($local->getOwningTable()->getModelName()).' = $'.$this->getNaming($local->getOwningTable()->getName()).';')
                        ->write('')
                        ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                    // getter
                    ->write('/**')
                    ->write(' * Get '.$local->getOwningTable()->getModelName().' entity (one to one).')
                    ->write(' *')
                    ->write(' * @return '.$typehints['get_phpdoc'])
                    ->write(' */')
                    ->write('public function get'.$local->getOwningTable()->getModelName().'()'.$typehints['get_return'])
                    ->write('{')
                    ->indent()
                        ->write('return $this->'.$this->getNaming($local->getOwningTable()->getName()).';')
                    ->outdent()
                    ->write('}')
                    ->write('')
                ;
            }
        }

        // 1 <=> N references
        foreach ($this->getAllForeignKeys() as $foreign) {
            if ($this->isForeignKeyIgnored($foreign)) {
                continue;
            }

            $this->getDocument()->addLog(sprintf('  Writing setter/getter for 1 <=> ? "%s"', $foreign->getParameters()->get('name')));

            if ($foreign->isManyToOne()) {
                $this->getDocument()->addLog(sprintf('  Applying setter/getter for "%s"', '1 <=> N'));

                $related = $this->getRelatedName($foreign);
                $related_text = $this->getRelatedName($foreign, false);
                $nameSingular = $this->getNaming($this->getRelatedVarName($foreign->getReferencedTable()->getModelName(), $related, false, $foreign));
                $variableNameSingular = $nameSingular;

                $nullable = true;
                foreach ($foreign->getLocals() as $lc) {
                    $nullable = $nullable && $lc->isNotNull();
                }

                $typehints = array(
                    'set_phpdoc_arg' => $this->typehint($foreign->getReferencedTable()->getNamespace(), true),
                    'set_phpdoc_return' => $this->typehint($this->getNamespace(), false),
                    'set_arg' => $this->paramTypehint($foreign->getReferencedTable()->getNamespace(), true),
                    'set_return' => $this->returnTypehint(null, false),

                    'get_phpdoc' => $this->typehint($foreign->getReferencedTable()->getNamespace(), true),
                    'get_return' => $this->returnTypehint($foreign->getReferencedTable()->getNamespace(), true),
                );

                $writer
                    // setter
                    ->write('/**')
                    ->write(' * Set '.trim($foreign->getReferencedTable()->getModelName().' entity '.$related_text).' (many to one).')
                    ->write(' *')
                    ->write(' * @param '.$typehints['set_phpdoc_arg'].' $'.$variableNameSingular)
                    ->write(' *')
                    ->write(' * @return '.$typehints['set_phpdoc_return'])
                    ->write(' */')
                    ->write('public function set'.ucfirst($nameSingular).'('.$typehints['set_arg'].'$'.$variableNameSingular.')'.$typehints['set_return'])
                    ->write('{')
                    ->indent()
                        ->write('$this->'.$variableNameSingular.' = $'.$variableNameSingular.';')
                        ->write('')
                        ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                    // getter
                    ->write('/**')
                    ->write(' * Get '.trim($foreign->getReferencedTable()->getModelName().' entity '.$related_text).' (many to one).')
                    ->write(' *')
                    ->write(' * @return '.$typehints['get_phpdoc'])
                    ->write(' */')
                    ->write('public function get'.ucfirst($nameSingular).'()'.$typehints['get_return'])
                    ->write('{')
                    ->indent()
                        ->write('return $this->'.$variableNameSingular.';')
                    ->outdent()
                    ->write('}')
                    ->write('')
                ;
            } else {
                $this->getDocument()->addLog(sprintf('  Applying setter/getter for "%s"', '1 <=> 1'));

                $nullable = true;
                foreach ($foreign->getLocals() as $lc) {
                    $nullable = $nullable && $lc->isNotNull();
                }

                $typehints = array(
                    'set_phpdoc_arg' => $this->typehint($foreign->getReferencedTable()->getNamespace(), $nullable),
                    'set_phpdoc_return' => $this->typehint($this->getNamespace(), false),
                    'set_arg' => $this->paramTypehint($foreign->getReferencedTable()->getNamespace(), $nullable),
                    'set_return' => $this->returnTypehint(null, false),

                    'get_phpdoc' => $this->typehint($foreign->getReferencedTable()->getNamespace(), $nullable),
                    'get_return' => $this->returnTypehint($foreign->getReferencedTable()->getNamespace(), true),
                );

                $writer
                    // setter
                    ->write('/**')
                    ->write(' * Set '.$foreign->getReferencedTable()->getModelName().' entity (one to one).')
                    ->write(' *')
                    ->write(' * @param '.$typehints['set_phpdoc_arg'].' $'.$this->getNaming($foreign->getReferencedTable()->getName()))
                    ->write(' *')
                    ->write(' * @return '.$typehints['set_phpdoc_return'])
                    ->write(' */')
                    ->write('public function set'.$foreign->getReferencedTable()->getModelName().'('.$typehints['set_arg'].'$'.$this->getNaming($foreign->getReferencedTable()->getName()).')'.$typehints['set_return'])
                    ->write('{')
                    ->indent()
                        ->write('$this->'.$this->getNaming($foreign->getReferencedTable()->getName()).' = $'.$this->getNaming($foreign->getReferencedTable()->getName()).';')
                        ->write('')
                        ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                    // getter
                    ->write('/**')
                    ->write(' * Get '.$foreign->getReferencedTable()->getModelName().' entity (one to one).')
                    ->write(' *')
                    ->write(' * @return '.$typehints['get_phpdoc'])
                    ->write(' */')
                    ->write('public function get'.$foreign->getReferencedTable()->getModelName().'()'.$typehints['get_return'])
                    ->write('{')
                    ->indent()
                        ->write('return $this->'.$this->getNaming($foreign->getReferencedTable()->getName()).';')
                    ->outdent()
                    ->write('}')
                    ->write('')
                ;
            }
        }

        return $this;
    }

    protected function writeManyToManyGetterAndSetter(WriterInterface $writer)
    {
        foreach ($this->getTableM2MRelations() as $relation) {
            $this->getDocument()->addLog(sprintf('  Writing N <=> N relation "%s"', $relation['refTable']->getModelName()));

            $fk2 = null;

            $isOwningSide = $this->getFormatter()->isOwningSide($relation, $fk2);

            $typehints = array(
                'add_phpdoc_arg' => $this->typehint($relation['refTable']->getNamespace(), false),
                'add_phpdoc_return' => $this->typehint($this->getNamespace($this->getModelName()), false),
                'add_arg' => $this->paramTypehint($relation['refTable']->getNamespace(), false),
                'add_return' => $this->returnTypehint(null, false),

                'remove_phpdoc_arg' => $this->typehint($relation['refTable']->getNamespace(), false),
                'remove_phpdoc_return' => $this->typehint($this->getNamespace($this->getModelName()), false),
                'remove_arg' => $this->paramTypehint($relation['refTable']->getNamespace(), false),
                'remove_return' => $this->returnTypehint(null, false),

                'get_phpdoc' => $this->typehint($this->getCollectionInterface(), false),
                'get_return' => $this->returnTypehint(null, false),
            );

            $variableNameSingular = $this->getNaming($this->getRelatedVarName($relation['refTable']->getModelName(), null, false, $fk2));
            $variableNamePlural = $this->getNaming($this->getRelatedVarName($relation['refTable']->getModelName(), null, true, $fk2));

            $writer
                ->write('/**')
                ->write(' * Add '.$relation['refTable']->getModelName().' entity to collection.')
                ->write(' *')
                ->write(' * @param '.$typehints['add_phpdoc_arg'].' $'.$variableNameSingular)
                ->write(' *')
                ->write(' * @return '.$typehints['add_phpdoc_return'])
                ->write(' */')
                ->write('public function add'.ucfirst($variableNameSingular).'('.$typehints['add_arg'].'$'.$variableNameSingular.')'.$typehints['add_return'])
                ->write('{')
                ->indent()
                    ->writeCallback(function(WriterInterface $writer, Table $_this = null) use ($isOwningSide, $relation, $variableNameSingular, $fk2) {
                        if ($isOwningSide) {
                            $writer->write('$%s->add%s($this);', $variableNameSingular, ucfirst($this->getNaming($this->getRelatedVarName($_this->getModelName(), null, false, $fk2))));
                        }
                    })
                    ->write('$this->'.$variableNamePlural.'[] = $'.$variableNameSingular.';')
                    ->write('')
                    ->write('return $this;')
                ->outdent()
                ->write('}')
                ->write('')
                ->write('/**')
                ->write(' * Remove '.$relation['refTable']->getModelName().' entity from collection.')
                ->write(' *')
                ->write(' * @param '.$typehints['remove_phpdoc_arg'].' $'.$variableNameSingular)
                ->write(' *')
                ->write(' * @return '.$typehints['remove_phpdoc_return'])
                ->write(' */')
                ->write('public function remove'.ucfirst($variableNameSingular).'('.$typehints['remove_arg'].'$'.$variableNameSingular.')'.$typehints['remove_return'])
                ->write('{')
                ->indent()
                    ->writeCallback(function(WriterInterface $writer, Table $_this = null) use ($isOwningSide, $relation, $variableNameSingular, $fk2) {
                        if ($isOwningSide) {
                            $writer->write('$%s->remove%s($this);', $variableNameSingular, ucfirst($this->getNaming($this->getRelatedVarName($_this->getModelName(), null, false, $fk2))));
                        }
                    })
                    ->write('$this->'.$variableNamePlural.'->removeElement($'.$variableNameSingular.');')
                    ->write('')
                    ->write('return $this;')
                ->outdent()
                ->write('}')
                ->write('')
                ->write('/**')
                ->write(' * Get '.$relation['refTable']->getModelName().' entity collection.')
                ->write(' *')
                ->write(' * @return '.$typehints['get_phpdoc'])
                ->write(' */')
                ->write('public function get'.ucfirst($variableNamePlural).'()'.$typehints['get_return'])
                ->write('{')
                ->indent()
                    ->write('return $this->'.$variableNamePlural.';')
                ->outdent()
                ->write('}')
                ->write('')
            ;
        }

        return $this;
    }

    /**
     * Write post class handler.
     *
     * @param \MwbExporter\Writer\WriterInterface $writer
     * @return \MwbExporter\Formatter\Doctrine2\Annotation\Model\Table
     */
    public function writePostClassHandler(WriterInterface $writer)
    {
        return $this;
    }

    public function writeSerialization(WriterInterface $writer)
    {
        $serialized = array();
        foreach ($this->getColumns() as $column) {
            if (!$column->isIgnored()) {
                $serialized[] = sprintf('\'%s\'', $column->getColumnName(false));
            }
        }
        $writer
            ->write('public function __sleep()')
            ->write('{')
            ->indent()
                ->write('return array(%s);', implode(', ', $serialized))
            ->outdent()
            ->write('}')
        ;

        return $this;
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

    protected function paramTypehint($type, $nullable)
    {
        if ($this->getConfig()->get(Formatter::CFG_PHP7_TYPEHINTS) &&
            $this->getConfig()->get(Formatter::CFG_PHP7_ARG_TYPEHINTS) &&
            strlen($type)) {
            return $this->typehint($type, $nullable).' ';
        }
    }

    protected function returnTypehint($type, $nullable)
    {
        if ($this->getConfig()->get(Formatter::CFG_PHP7_TYPEHINTS) &&
            $this->getConfig()->get(Formatter::CFG_PHP7_RETURN_TYPEHINTS) &&
            strlen($type)) {
            return ': '.$this->typehint($type, $nullable);
        }
    }

    /**
     * Get name using naming strategy.
     *
     * @param string $name
     * @param string $strategy
     * @return string
     */
    public function getNaming($name, $strategy = null)
    {
        $strategy = $strategy ?: $this->getConfig()->get(Formatter::CFG_NAMING_STRATEGY);
        switch ($strategy) {
            case Formatter::NAMING_AS_IS:
                break;
            case Formatter::NAMING_CAMEL_CASE:
                $name = lcfirst($this->beautify($name));
                break;
            case Formatter::NAMING_PASCAL_CASE:
                $name = $this->beautify($name);
                break;
        }

        return $name;
    }
}
