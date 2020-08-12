# README

This is an exporter to convert [MySQL Workbench](http://www.mysql.com/products/workbench/) Models (\*.mwb) to a Doctrine 2 Schema.

## Prerequisites

  * PHP 5.4+
  * Composer to install the dependencies

## Installation

```
php composer.phar require --dev mysql-workbench-schema-exporter/doctrine2-exporter
```

This will install the exporter and also require [mysql-workbench-schema-exporter](https://github.com/mysql-workbench-schema-exporter/mysql-workbench-schema-exporter).

You then can invoke the CLI script using `vendor/bin/mysql-workbench-schema-export`.

## Formatter Setup Options

Additionally to the [common options](https://github.com/mysql-workbench-schema-exporter/mysql-workbench-schema-exporter#configuring-mysql-workbench-schema-exporter) of mysql-workbench-schema-exporter these options are supported:

Common Setup Options for Doctrine 2.0:

  * `useAutomaticRepository`

    Automatically generate entity repository class name.

  * `bundleNamespace`

    The global namespace prefix for entity class name.

  * `entityNamespace`

    The entity namespace.

    Default is `Entity`.

  * `repositoryNamespace`

    The namespace prefix for entity repository class name. For this configuration to apply,
    `useAutomaticRepository` must be set to `true`.

  * `skipColumnWithRelation`

    Don't generate columns definition (for YAML) or columns variable and columns getter and setter
    (for Annotation) which has relation to other table.

    Default is `false`.

  * `relatedVarNameFormat`

    The format for generated related column name.

    Default is `%name%%related%`.

  * `nullableAttribute`

    How nullable attribute of columns and joins is generated. Set to `auto` if you want to
    automatically include nullable attribute based on its value. Set to `always` to always
    include nullable attribute.

    Default is `auto`.

  * `generatedValueStrategy`

    The stragety for auto-generated values.

    Default is `auto`.

  * `defaultCascade`

    The default cascade option to define.

    Default is `false`.

### Doctrine 2.0 YAML Schema

#### Setup Options

  * `extendTableNameWithSchemaName`

    Include schema name beside the table name.

    Default is `false`.

### Doctrine 2.0 Annotation

#### Setup Options

  * `useAnnotationPrefix`

    Doctrine annotation prefix.

    Default is `ORM\`.

  * `skipGetterAndSetter`

    Don't generate columns getter and setter.

    Default is `false`.

  * `generateEntitySerialization`

    Generate method `__sleep()` to include only real columns when entity is serialized.

    Default is `true`.

  * `generateExtendableEntity`

    Generate two class for each tables in schema, one for base and one other for extend class.
    The extend class would not be generated if it already exist. So it is safe to place custom code
    inside the extend class.

    This option will generate entity using Single Table Inheritance.

    Default is `false`.

  * `extendableEntityHasDiscriminator`

    If enabled, `DiscriminatorColumn` and `DiscriminatorMap` annotations will be used.

    Default is `true`.

  * `quoteIdentifierStrategy`

    This option determine wheter identifier quoting is applied or not, depend on the strategy
    value.

    * `auto`, indentifier quoting enabled if identifier is a reserved word.
    * `always`, always quote identifier.
    * `none`, never quote identifier.

    Default is `auto`.

  * `extendsClass`

    This option allows you to define a base class from which all generated entities extend.

    Default is `''`.

  * `propertyTypehint`

    This option allows you to specify whether type-hinting should be enabled for all *non-scalar* properties
    whose type is a class. E.g. `\DateTime` would be type-hinted but not `object` or `string`.

    Default is `false`.
    
  * `useBehavioralExtensions`

    use Doctrine2 behavioral extensions like create table with name '_img' then can be auto create plugin support.

    Default is `false`.
#### Model Comment Behavior

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
    
    This tag can also be used on Many to Many tables (in comments as well), to change the relation names used in each of the 2 tables.
    For example, on a store_product_category m2m table, having two foreign keys only (store_product_id and store_category_id), we could have the following:
    
        {d:relatedNames}
        StoreProduct:Product
        StoreCategory:Category
        {/d:relatedNames}
        
    This will generate `products` and `categories` collection properties (along with methods for adding and removing items) on each of the two entities. 
    
   * `{d:relationNames}oneToManyName:manyToOneName{/d:relationNames}` (applied to ForeignKey)
  
     Overrides `relatedVarNameFormat` and the `relatedNames` tag from above. Useful when one table references another table multiple times, with multiple foreign keys.
     
     The two names should be the desired field names that this relation would be represented by in the two model classes.
     
     For example, considering a Ticket class and a User class. A ticket could reference the User class with two foreign keys: owner_id and assigned_user_id.
     
     On the Tickets table we could use 
        - `{d:relationNames}Owner:OwnedTickets{/d:relationNames}` on the owner_id foreign key
        and
        - `{d:relationNames}AssignedUser:AssignedTickets{/d:relationNames}` on the assigned_user_id foreign key
     to override the field names, so a Ticket will have `owner` and `assignedUser` fields, while User will have `ownedTickets` and `assignedTickets`.
     
     Ideally these names would be CamelCase. The oneToMany would be singular and the ManyToOne would be plural.

### Doctrine 2.0 Annotation with ZF2 Input Filter Classes

Doctrine 2.0 Annotation with ZF2 Input Filter Classes formatter directly extend Doctrine 2.0
Annotation. The setup options and model comment behavior exactly the same as Doctrine 2.0
Annotation with the following addons.

Add Some `Zend\Validator\StringLength`,`Zend\Filter\StringTrim`,`Zend\Filter\StripTags` as string type column
Add Some Filters like column name with 'phone' or '_tel' auto create filter with `Zend\I18n\Validator\PhoneNumber`
and column name with 'email' auto create filter with `Zend\Validator\EmailAddress`


#### Setup Options

  * `generateEntityPopulate`

    Generate `populate()` method for entity class.

    Default is `true`.

  * `generateEntityGetArrayCopy`

    Generate `getArrayCopy()` method for entity class.

    Default is `true`.

## Command Line Interface (CLI)

See documentation for [mysql-workbench-schema-exporter](https://github.com/mysql-workbench-schema-exporter/mysql-workbench-schema-exporter#command-line-interface-cli)

## Links

  * [MySQL Workbench](http://wb.mysql.com/)
  * [Doctrine Project](http://www.doctrine-project.org/)
