# Doctrine 2.0 YAML Configuration

Auto generated at 2023-04-08T00:04:14+0700.

## Global Configuration

  * `language`

    Language detemines which language used to transform singular and plural words
    used in certains other schema like Doctrine.

    Valid values: `english`, `french`, `norwegian-bokmal`, `portuguese`, `spanish`, `turkish`

    Default value: `english`

  * `useTab` (alias: `useTabs`)

    Use tab as blank instead of space in generated files.

    Default value: `false`

  * `indentation`

    Number of spaces used as blank in generated files.

    Default value: `4`

  * `eolDelimiter` (alias: `eolDelimeter`)

    End of line (EOL) delimiter detemines the end of line in generated files.

    Valid values: `win`, `unix`

    Default value: `win`

  * `filename`

    The output filename format, use the following tag `%schema%`, `%table%`, `%entity%`, and
    `%extension%` to allow the filename to be replaced with contextual data.

    Default value: `%entity%.dcm.%extension%`

  * `backupExistingFile`

    Perform backup existing file before writing generated file.

    Backup file will have an extension of *.bak.

    Default value: `true`

  * `headerFile`

    Include file as header in the generated files. It will be wrapped as a
    comment by choosen formatter. This configuration useful as for example
    to include notice to generated files such as license file.

    Default value: `blank`

  * `addGeneratorInfoAsComment`

    Include generated information as a comment in generated files.

    Default value: `true`

  * `namingStrategy`

    Naming strategy detemines how objects, variables, and methods name will be generated.

    Valid values: `as-is`, `camel-case`, `pascal-case`

    Default value: `as-is`

  * `identifierStrategy`

    Determines how identifier like table name will be treated for generated
    entity/model name. Supported identifier strategies are `fix-underscore`
    which will fix for double underscore to single underscore, and `none` which
    will do nothing.

    Valid values: `none`, `fix-underscore`

    Default value: `none`

  * `cleanUserDatatypePrefix` (alias: `asIsUserDatatypePrefix`)

    Clean user datatype matched the prefix specified.

    Default value: `blank`

  * `enhanceManyToManyDetection`

    Allows generate additional model for many to many relations.

    Default value: `true`

  * `sortTableAndView` (alias: `sortTablesAndViews`)

    Perform table name and view name sorting before generating files.

    Default value: `true`

  * `skipManyToManyTables`

    Skip many to many table generation.

    Default value: `true`

  * `skipPluralNameChecking`

    Skip checking the plural name of model and leave as is, useful for non English
    table names.

    Default value: `false`

  * `exportOnlyInCategory` (alias: `exportOnlyTableCategorized`)

    Some models may have category defined in comment, process only if it is matched.

    Default value: `blank`

  * `logToConsole`

    Activate logging to console.

    Default value: `false`

  * `logFile`

    Activate logging to filename.

    Default value: `blank`

  * `useLoggedStorage`

    Useful to use the generated files content for further processing.

    Default value: `false`

## Doctrine 2.0 Global Configuration

  * `useAutomaticRepository`

    Automatically generate entity repository class name.

    Default value: `true`

  * `repositoryNamespace`

    The namespace prefix for entity repository class name. For this configuration to apply,
    `useAutomaticRepository` must be set to `true`.

    Default value: `blank`

  * `bundleNamespace`

    The global namespace prefix for entity class name.

    Default value: `blank`

  * `entityNamespace`

    The entity namespace.

    Default value: `blank`

  * `tablenamePrefix` (alias: `prefixTablename`)

    Add prefix to table name.

    Default value: `blank`

  * `relatedVarNameFormat`

    The format for generated related column name.

    Default value: `%name%_%related%`

  * `skipColumnWithRelation`

    Don't generate columns definition (for YAML) or columns variable, columns getter and setter
    (for Annotation) which has relation to other table.

    Default value: `false`

  * `generatedValueStrategy`

    The stragety for auto-generated values.

    Valid values: `auto`, `identity`, `sequence`, `table`, `none`

    Default value: `auto`

  * `nullableAttribute`

    How nullable attribute of columns and joins is generated. Set to `auto` if you want to
    automatically include nullable attribute based on its value. Set to `always` to always
    include nullable attribute.

    Valid values: `auto`, `always`

    Default value: `auto`

  * `defaultCascade`

    The default cascade option to define.

    Valid values: ``, `persist`, `remove`, `merge`, `detach`, `all`, `refresh`

    Default value: `false`

## Doctrine 2.0 Yaml Configuration

  * `extendTableNameWithSchemaName`

    Include schema name in table name.

    Default value: `false`

