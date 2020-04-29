<?php

namespace Drupal\Driver\Database\sqlsrv;

use Drupal\Core\Database\Connection as DatabaseConnection;
use Drupal\Core\Database\Query\Condition as QueryCondition;
use Drupal\Core\Database\Query\PlaceholderInterface;

/**
 * @addtogroup database
 * @{
 */
class Condition extends QueryCondition {

  /**
   * {@inheritdoc}
   *
   * Overridden to replace REGEXP expressions.
   * Should this move to Condition::condition()?
   */
  public function compile(DatabaseConnection $connection, PlaceholderInterface $queryPlaceholder) {
    // Find any REGEXP conditions and turn them into function calls.
    foreach ($this->conditions as &$condition) {
      if (isset($condition['operator'])) {
        if ($condition['operator'] == 'REGEXP' || $condition['operator'] == 'NOT REGEXP') {

          /** @var \Drupal\Driver\Database\sqlsrv\Schema $schema*/
          $schema = $connection->schema();
          $schema_name = $schema->getDefaultSchema();
          $placeholder = ':db_condition_placeholder_' . $queryPlaceholder->nextPlaceholder();
          $field_fragment = $connection->escapeField($condition['field']);
          $comparison = $condition['operator'] == 'REGEXP' ? '1' : '0';
          $condition['field'] = "{$schema_name}.REGEXP({$placeholder}, {$field_fragment}) = {$comparison}";
          $condition['operator'] = NULL;
          $condition['value'] = [$placeholder => $condition['value']];
        }
        // Drupal expects all LIKE expressions to be escaped with a backslash.
        // Due to a PDO bug sqlsrv uses its default escaping behavior.
        // This can be removed if https://bugs.php.net/bug.php?id=79276 is
        // fixed.
        elseif ($condition['operator'] == 'LIKE' || $condition['operator'] == 'NOT LIKE') {
          $condition['value'] = strtr($condition['value'], [
            '[' => '[[]',
            '\%' => '[%]',
            '\_' => '[_]',
            '\\\\' => '\\',
          ]);
        }
        elseif ($condition['operator'] == 'PREFIX_SCHEMA') {
          /** @var \Drupal\Driver\Database\sqlsrv\Schema $schema*/
          $schema = $connection->schema();
          $schema_name = $schema->getDefaultSchema();
          $condition['field'] = $schema_name . '.' . $condition['field'];
          $condition['operator'] = NULL;
        }
      }
    }
    parent::compile($connection, $queryPlaceholder);
  }

  /**
   * {@inheritdoc}
   *
   * Overridden to replace REGEXP expressions and CONCAT_WS.
   *
   * CONCAT_WS is supported in SQL Server 2017.
   * Needs to be tested for complex nested expressions.
   * Need to handle LIKE in a WHERE clause.
   */
  public function where($snippet, $args = []) {
    $operator = NULL;
    if (strpos($snippet, " NOT REGEXP ") !== FALSE) {
      $operator = ' NOT REGEXP ';
    }
    elseif (strpos($snippet, " REGEXP ") !== FALSE) {
      $operator = ' REGEXP ';
    }
    if ($operator !== NULL) {
      $fragments = explode($operator, $snippet);
      $field = $fragments[0];
      $value = $fragments[1];
      $comparison = $operator == ' REGEXP ' ? '1' : '0';

      $snippet = "REGEXP({$value}, {$field}) = {$comparison}";
      // We need the connection in order to add the schema.
      $operator = 'PREFIX_SCHEMA';
    }
    if (($pos1 = stripos($snippet, 'CONCAT_WS(')) !== FALSE) {
      // We assume the the separator does not contain any single-quotes
      // and none of the arguments contain commas.
      $pos2 = $this->findParenMatch($snippet, $pos1 + 9);
      $argument_list = substr($snippet, $pos1 + 10, $pos2 - 10 - $pos1);
      $arguments = explode(', ', $argument_list);
      $closing_quote_pos = stripos($argument_list, '\'', 1);
      $separator = substr($argument_list, 1, $closing_quote_pos - 1);
      $strings_list = substr($argument_list, $closing_quote_pos + 3);
      $arguments = explode(', ', $strings_list);
      $replace = "STUFF(";
      $coalesce = [];
      foreach ($arguments as $argument) {
        $coalesce[] = "COALESCE('{$separator}' + {$argument}, '')";
      }
      $coalesce_string = implode(' + ', $coalesce);
      $sep_len = strlen($separator);
      $replace = "STUFF({$coalesce_string}, 1, {$sep_len}, '')";
      $snippet = substr($snippet, 0, $pos1) . $replace . substr($snippet, $pos2 + 1);
      $operator = NULL;
    }
    $this->conditions[] = [
      'field' => $snippet,
      'value' => $args,
      'operator' => $operator,
    ];
    $this->changed = TRUE;
    return $this;
  }

  /**
   * Given a string find the matching parenthesis after the given point.
   *
   * @param string $string
   *   The input string.
   * @param int $start_paren
   *   The 0 indexed position of the open-paren, for which we would like
   *   to find the matching closing-paren.
   *
   * @return int|false
   *   The 0 indexed position of the close paren.
   */
  private function findParenMatch($string, $start_paren) {
    if ($string[$start_paren] !== '(') {
      return FALSE;
    }
    $str_array = str_split(substr($string, $start_paren + 1));
    $paren_num = 1;
    foreach ($str_array as $i => $char) {
      if ($char == '(') {
        $paren_num++;
      }
      elseif ($char == ')') {
        $paren_num--;
      }
      if ($paren_num == 0) {
        return $i + $start_paren + 1;
      }
    }
    return FALSE;
  }

}

/**
 * @} End of "addtogroup database".
 */
