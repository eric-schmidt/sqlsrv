<?php

namespace Drupal\Tests\phpunit_example\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\Core\Database\Stub\StubConnection;
use Drupal\Driver\Database\sqlsrv\Connection;

/**
 * @coversDefaultClass \Drupal\Driver\Database\sqlsrv\Connection
 * @group Database
 */

class ConnectionTest extends UnitTestCase {

  /**
   * Mock PDO object for use in tests.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Tests\Core\Database\Stub\StubPDO
   */
  protected $mockPdo;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->mockPdo = $this->createMock('Drupal\Tests\Core\Database\Stub\StubPDO');
  }

  /**
   * Data provider for testEscapeTable.
   *
   * @return array
   *   An indexed array of where each value is an array of arguments to pass to
   *   testEscapeField. The first value is the expected value, and the second
   *   value is the value to test.
   */
  public function providerEscapeTables() {
    return [
      ['nocase', 'nocase'],
      ['"camelCase"', 'camelCase'],
      ['"camelCase"', '"camelCase"'],
      ['"camelCase"', 'camel/Case'],
      // Sometimes, table names are following the pattern database.schema.table.
      ['"camelCase".nocase.nocase', 'camelCase.nocase.nocase'],
      ['nocase."camelCase".nocase', 'nocase.camelCase.nocase'],
      ['nocase.nocase."camelCase"', 'nocase.nocase.camelCase'],
      ['"camelCase"."camelCase"."camelCase"', 'camelCase.camelCase.camelCase'],
    ];
  }

  /**
   * Data provider for testEscapeAlias.
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - Expected escaped string.
   *   - String to escape.
   */
  public function providerEscapeAlias() {
    return [
      ['nocase', 'nocase'],
      ['"camelCase"', '"camelCase"'],
      ['"camelCase"', 'camelCase'],
      ['"camelCase"', 'camel.Case'],
    ];
  }

  /**
   * Data provider for testEscapeField.
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - Expected escaped string.
   *   - String to escape.
   */
  public function providerEscapeFields() {
    return [
      ['title', 'title'],
      ['"isDefaultRevision"', 'isDefaultRevision'],
      ['"isDefaultRevision"', '"isDefaultRevision"'],
      ['entity_test."isDefaultRevision"', 'entity_test.isDefaultRevision'],
      ['entity_test."isDefaultRevision"', '"entity_test"."isDefaultRevision"'],
      ['"entityTest"."isDefaultRevision"', '"entityTest"."isDefaultRevision"'],
      ['"entityTest"."isDefaultRevision"', 'entityTest.isDefaultRevision'],
      ['entity_test."isDefaultRevision"', 'entity_test.is.Default.Revision'],
    ];
  }

  /**
   * @covers ::escapeTable
   * @dataProvider providerEscapeTables
   */
  public function testEscapeTable($expected, $name) {
    $pgsql_connection = new Connection($this->mockPdo, []);

    $this->assertEquals($expected, $pgsql_connection->escapeTable($name));
  }

  /**
   * @covers ::escapeAlias
   * @dataProvider providerEscapeAlias
   */
  public function testEscapeAlias($expected, $name) {
    $pgsql_connection = new Connection($this->mockPdo, []);

    $this->assertEquals($expected, $pgsql_connection->escapeAlias($name));
  }

  /**
   * @covers ::escapeField
   * @dataProvider providerEscapeFields
   */
  public function testEscapeField($expected, $name) {
    $pgsql_connection = new Connection($this->mockPdo, []);

    $this->assertEquals($expected, $pgsql_connection->escapeField($name));
  }
}
