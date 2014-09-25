<?php

/**
 * @file
 * Definition of Drupal\sqlsrv\Tests\SelectQueryTest.
 */

namespace Drupal\sqlsrv\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\Core\Database\Database;

/**
 * General tests for SQL Server database driver.
 *
 * @group Database
 */
class SelectQueryTest extends WebTestBase {

  public static function getInfo() {
    return [
      'name' => 'SQLServer form protections',
      'description' => 'Ensure that SQLServer protects site forms properly.',
      'group' => 'SQLServer',
    ];
  }

  /**
   * The select query object to test.
   *
   * @var \Drupal\Core\Database\Query\Select
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    // Get a connection to use during testing.
    $connection = Database::getConnection();
  }

  /**
   * Checks that invalid sort directions in ORDER BY get converted to ASC.
   */
  public function testGroupByExpansion() {
    $table_spec = array(
        'fields' => array(
          'id'  => array(
            'type' => 'serial',
            'not null' => TRUE,
          ),
          'task' => array(
            'type' => 'varchar',
            'length' => 255,
            'not null' => TRUE,
          ),
        ),
      );

    db_create_table('test_task', $table_spec);
    db_insert('test_task')->fields(array('task' => 'eat'))->execute();
    db_insert('test_task')->fields(array('task' => 'sleep'))->execute();
    db_insert('test_task')->fields(array('task' => 'sleep'))->execute();
    db_insert('test_task')->fields(array('task' => 'code'))->execute();
    db_insert('test_task')->fields(array('task' => 'found new band'))->execute();
    db_insert('test_task')->fields(array('task' => 'perform at superbowl'))->execute();

    // By ANSI SQL, GROUP BY columns cannot use aliases. Test that the
    // driver expands the aliases properly.
    $query = db_select('test_task', 't');
    $count_field = $query->addExpression('COUNT(task)', 'num');
    $task_field = $query->addExpression('CONCAT(:prefix, t.task)', 'task', array(':prefix' => 'Task: '));
    $query->orderBy($count_field);
    $query->groupBy($task_field);
    $result = $query->execute();

    $num_records = 0;
    $last_count = 0;
    $records = array();
    foreach ($result as $record) {
      $num_records++;
      $this->assertTrue($record->$count_field >= $last_count, 'Results returned in correct order.');
      $last_count = $record->$count_field;
      $records[$record->$task_field] = $record->$count_field;
    }

    $correct_results = array(
      'Task: eat' => 1,
      'Task: sleep' => 2,
      'Task: code' => 1,
      'Task: found new band' => 1,
      'Task: perform at superbowl' => 1,
    );

    foreach ($correct_results as $task => $count) {
      $this->assertEqual($records[$task], $count, "Correct number of '@task' records found.");
    }

    $this->assertEqual($num_records, 5, 'Returned the correct number of total rows.');
  }

}
