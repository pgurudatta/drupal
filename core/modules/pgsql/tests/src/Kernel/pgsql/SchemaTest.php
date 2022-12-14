<?php

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\KernelTests\Core\Database\DriverSpecificSchemaTestBase;

/**
 * Tests schema API for the PostgreSQL driver.
 *
 * @group Database
 */
class SchemaTest extends DriverSpecificSchemaTestBase {

  /**
   * {@inheritdoc}
   */
  public function checkSchemaComment(string $description, string $table, string $column = NULL): void {
    $this->assertSame($description, $this->schema->getComment($table, $column), 'The comment matches the schema description.');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkSequenceRenaming(string $tableName): void {
    // For PostgreSQL, we also need to check that the sequence has been renamed.
    // The initial name of the sequence has been generated automatically by
    // PostgreSQL when the table was created, however, on subsequent table
    // renames the name is generated by Drupal and can not be easily
    // re-constructed. Hence we can only check that we still have a sequence on
    // the new table name.
    $sequenceExists = (bool) $this->connection->query("SELECT pg_get_serial_sequence('{" . $tableName . "}', 'id')")->fetchField();
    $this->assertTrue($sequenceExists, 'Sequence was renamed.');

    // Rename the table again and repeat the check.
    $anotherTableName = strtolower($this->getRandomGenerator()->name(63 - strlen($this->getDatabasePrefix())));
    $this->schema->renameTable($tableName, $anotherTableName);

    $sequenceExists = (bool) $this->connection->query("SELECT pg_get_serial_sequence('{" . $anotherTableName . "}', 'id')")->fetchField();
    $this->assertTrue($sequenceExists, 'Sequence was renamed.');
  }

  /**
   * @covers \Drupal\pgsql\Driver\Database\pgsql\Schema::introspectIndexSchema
   */
  public function testIntrospectIndexSchema(): void {
    $table_specification = [
      'fields' => [
        'id'  => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
        ],
        'test_field_1'  => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
        ],
        'test_field_2'  => [
          'type' => 'int',
          'default' => 0,
        ],
        'test_field_3'  => [
          'type' => 'int',
          'default' => 0,
        ],
        'test_field_4'  => [
          'type' => 'int',
          'default' => 0,
        ],
        'test_field_5'  => [
          'type' => 'int',
          'default' => 0,
        ],
      ],
      'primary key' => ['id', 'test_field_1'],
      'unique keys' => [
        'test_field_2' => ['test_field_2'],
        'test_field_3_test_field_4' => ['test_field_3', 'test_field_4'],
      ],
      'indexes' => [
        'test_field_4' => ['test_field_4'],
        'test_field_4_test_field_5' => ['test_field_4', 'test_field_5'],
      ],
    ];

    $table_name = strtolower($this->getRandomGenerator()->name());
    $this->schema->createTable($table_name, $table_specification);

    unset($table_specification['fields']);

    $introspect_index_schema = new \ReflectionMethod(get_class($this->schema), 'introspectIndexSchema');
    $introspect_index_schema->setAccessible(TRUE);
    $index_schema = $introspect_index_schema->invoke($this->schema, $table_name);

    // The PostgreSQL driver is using a custom naming scheme for its indexes, so
    // we need to adjust the initial table specification.
    $ensure_identifier_length = new \ReflectionMethod(get_class($this->schema), 'ensureIdentifiersLength');
    $ensure_identifier_length->setAccessible(TRUE);

    foreach ($table_specification['unique keys'] as $original_index_name => $columns) {
      unset($table_specification['unique keys'][$original_index_name]);
      $new_index_name = $ensure_identifier_length->invoke($this->schema, $table_name, $original_index_name, 'key');
      $table_specification['unique keys'][$new_index_name] = $columns;
    }

    foreach ($table_specification['indexes'] as $original_index_name => $columns) {
      unset($table_specification['indexes'][$original_index_name]);
      $new_index_name = $ensure_identifier_length->invoke($this->schema, $table_name, $original_index_name, 'idx');
      $table_specification['indexes'][$new_index_name] = $columns;
    }

    $this->assertEquals($table_specification, $index_schema);
  }

  /**
   * @covers \Drupal\Core\Database\Driver\pgsql\Schema::extensionExists
   */
  public function testPgsqlExtensionExists(): void {
    // Test the method for a non existing extension.
    $this->assertFalse($this->schema->extensionExists('non_existing_extension'));

    // Test the method for an existing extension.
    $this->assertTrue($this->schema->extensionExists('pg_trgm'));
  }

}
