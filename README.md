![Build Status](https://github.com/mysql-workbench-schema-exporter/doctrine2-exporter/actions/workflows/continuous-integration.yml/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/mysql-workbench-schema-exporter/doctrine2-exporter/v/stable.svg)](https://packagist.org/packages/mysql-workbench-schema-exporter/doctrine2-exporter)
[![Total Downloads](https://poser.pugx.org/mysql-workbench-schema-exporter/doctrine2-exporter/downloads.svg)](https://packagist.org/packages/mysql-workbench-schema-exporter/doctrine2-exporter) 
[![License](https://poser.pugx.org/mysql-workbench-schema-exporter/doctrine2-exporter/license.svg)](https://packagist.org/packages/mysql-workbench-schema-exporter/doctrine2-exporter)

# README

This is an exporter to convert [MySQL Workbench](http://www.mysql.com/products/workbench/) Models (\*.mwb) to a Doctrine 2 Schema.

## Prerequisites

  * PHP 7.4+
  * Composer to install the dependencies

## Installation

```
composer require --dev mysql-workbench-schema-exporter/doctrine2-exporter
```

This will install the exporter and also require [mysql-workbench-schema-exporter](https://github.com/mysql-workbench-schema-exporter/mysql-workbench-schema-exporter).

You then can invoke the CLI script using `vendor/bin/mysql-workbench-schema-export`.

## Configuration

  * [Doctrine 2.0 YAML Schema](/docs/doctrine2-yaml.md)
  * [Doctrine 2.0 Annotation](/docs/doctrine2-annotation.md)
  * [Doctrine 2.0 Annotation with ZF2 Input Filter Classes](/docs/doctrine2-zf2inputfilterannotation.md)

## Model Comment Behavior

  * `{d:bundleNamespace}AcmeBundle{/d:bundleNamespace}` (applied to Table)

    Override `bundleNamespace` option.

  * `{d:m2m}false{/d:m2m}` (applied to Table)

    MySQL Workbench Schema Exporter tries to automatically guess which tables are many-to-many
    mapping tables and will not generate entity classes for these tables.

    A table is considered a mapping table, if it contains exactly two foreign keys to different
    tables and those tables are not many-to-many mapping tables.

    Sometimes this guessing is incorrect for you. But you can add a hint in the comment of the
    table, to show that it is no mapping table. Just use `{d:m2m}false{/d:m2m}` anywhere in the
    comment of the table.

  * `{d:unidirectional}true{/d:unidirectional}` (applied to ForeignKey)

    All foreign keys will result in a bidirectional relation by default. If you only want a
    unidirectional relation, add a flag to the comment of the foreign key.

  * `{d:owningSide}true{/d:owningSide}` (applied to ForeignKey)

    In a bi-directional many-to-many mapping table the owning side of the relation is randomly
    selected. If you add this hint to one foreign key of the m2m-table, you can define the owning
    side for Doctrine.

  * `{d:cascade}persist, merge, remove, detach, all{/d:cascade}` (applied to ForeignKey)

    You can specify Doctrine cascade options as a comment on a foreign key. They will be generated
    into the Annotation.
    ([Reference](http://doctrine-orm.readthedocs.org/en/latest/reference/working-with-associations.html#transitive-persistence-cascade-operations))

  * `{d:fetch}EAGER{/d:fetch}` (applied to ForeignKey)

    You can specify the fetch type for relations in the comment of a foreign key. (EAGER or LAZY,
    doctrine default is LAZY)

  * `{d:orphanRemoval}true{/d:orphanRemoval}` (applied to ForeignKey)

    Another option you can set in the comments of foreign key.
    ([Reference](http://doctrine-orm.readthedocs.org/en/latest/reference/working-with-associations.html#orphan-removal))

  * `{d:order}column{/d:order}` (applied to ForeignKey)

    Apply OrderBy annotation to One To Many and Many To Many relation. OrderBy annotation can be
    written in the following format:

        column[,(asc|desc)]

    Multiple columns are supported, separated by line break. Example usage:

        {d:order}
          column1
          column1,desc
        {/d:order}

  * `{d:cache}READ_ONLY, NONSTRICT_READ_WRITE, READ_WRITE{/d:cache}` (applied to Table and/or ForeignKey)

    You can specify Doctrine second level caching strategy as a comment on a table or foreign key. They will be generated into the Annotation or YAML.
    ([Reference](http://doctrine-orm.readthedocs.io/en/latest/reference/second-level-cache.html))
    
    
  * `{d:relatedNames}RelationTable:NewName{/d:relatedNames}` (applied to Table)
    
    Overrides `relatedVarNameFormat`.

    Rename generated related column names when the table names and the `relatedVarNameFormat` pattern are not good enough. The format should be CamelCase singular and should map with the class name that is generated for the related entity.
    Can be written in the following format:

        RelationTableName:CustomRelationName

    Multiple relations are supported, separated by line break. Example usage:
        - on a "store_products" table with "store_product_categories" and "store_product_images" related tables:
        
        {d:relatedNames}
        StoreProductCategory:Category
        StoreProductImage:Image
        {/d:relatedNames}
        
    It can be used in both parent / child tables. For example, on a "store_product_images" table:
    
        {d:relatedNames}
        StoreProduct:Product
        {/d:relatedNames}
        
    The generated StoreProduct class will have "category" and "image" properties instead of "storeProductCategory" and "storeProductImage", while the "StoreProductImage" class will have a "product" property instead of "storeProduct".

## Command Line Interface (CLI)

See documentation for [mysql-workbench-schema-exporter](https://github.com/mysql-workbench-schema-exporter/mysql-workbench-schema-exporter#command-line-interface-cli)

## Links

  * [MySQL Workbench](http://wb.mysql.com/)
  * [Doctrine Project](http://www.doctrine-project.org/)
