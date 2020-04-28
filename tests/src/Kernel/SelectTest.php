<?php

namespace Drupal\Tests\sqlsrv\Kernel;

use Drupal\KernelTests\Core\Database\DatabaseTestBase;

/**
 * Tests the Select query builder.
 *
 * @group Database
 */
class SelectTest extends DatabaseTestBase {

  /**
   * Tests namespace of the condition and having objects.
   */
  public function testNamespaceConditionAndHavingObjects() {
    $namespace = (new \ReflectionObject($this->connection))->getNamespaceName() . "\\Condition";
    $select = $this->connection->select('test');
    $reflection = new \ReflectionObject($select);

    $condition_property = $reflection->getProperty('condition');
    $condition_property->setAccessible(TRUE);
    $this->assertIdentical($namespace, get_class($condition_property->getValue($select)));

    $having_property = $reflection->getProperty('having');
    $having_property->setAccessible(TRUE);
    $this->assertIdentical($namespace, get_class($having_property->getValue($select)));

    $nested_and_condition = $select->andConditionGroup();
    $this->assertIdentical($namespace, get_class($nested_and_condition));
    $nested_or_condition = $select->orConditionGroup();
    $this->assertIdentical($namespace, get_class($nested_or_condition));
  }

  /**
   * Test the CONCAT_WS function.
   *
   * @dataProvider dataProviderForTestConcatWs
   */
  public function testConcatWs($separator) {
    $query = $this->connection->select('test');
    $name_field = $query->addField('test', 'name');
    $job_field = $query->addField('test', 'job');
    $concat_field = $query->addExpression('CONCAT_WS(\'' . $separator . '\', name, job)');
    $results = $query->execute();
    while ($row = $results->fetchAssoc()) {
      $this->assertEqual($row[$concat_field], $row[$name_field] . '-'. $row[$job_field]);
    }
  }

  public function dataProviderForTestConcatWs() {
    return [
      'dash' => ['-'],
      'comma' => [','],
      'comma-space' => [', '],
    ];
  }

}
