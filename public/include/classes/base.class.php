<?php

// Make sure we are called from index.php
if (!defined('SECURITY'))
  die('Hacking attempt');

/**
 * Our base class that we extend our other classes from
 *
 * It supplies some basic features as cross-linking with other classes
 * after loading a newly created class.
 **/
class Base {
  private $sError = '';
  private $sCronError = '';
  protected $table = '';
  private $values = array(), $types = ''; 

  public function getTableName() {
    return $this->table;
  }
  public function setDebug($debug) {
    $this->debug = $debug;
  }
  public function setMysql($mysqli) {
    $this->mysqli = $mysqli;
  }
  public function setMail($mail) {
    $this->mail = $mail;
  }
  public function setSalt($salt) {
    $this->salt = $salt;
  }
  public function setSmarty($smarty) {
    $this->smarty = $smarty;
  }
  public function setUser($user) {
    $this->user = $user;
  }
  public function setConfig($config) {
    $this->config = $config;
  }
  public function setErrorCodes(&$aErrorCodes) {
    $this->aErrorCodes =& $aErrorCodes;
  }
  public function setToken($token) {
    $this->token = $token;
  }
  public function setBlock($block) {
    $this->block = $block;
  }
  public function setTransaction($transaction) {
    $this->transaction = $transaction;
  }
  public function setMemcache($memcache) {
    $this->memcache = $memcache;
  }
  public function setStatistics($statistics) {
    $this->statistics = $statistics;
  }
  public function setSetting($setting) {
    $this->setting = $setting;
  }
  public function setTools($tools) {
    $this->tools = $tools;
  }
  public function setBitcoin($bitcoin) {
    $this->bitcoin = $bitcoin;
  }
  public function setTokenType($tokentype) {
    $this->tokentype = $tokentype;
  }
  public function setShare($share) {
    $this->share = $share;
  }
  public function setTeamsAccounts($teamsaccounts) {
    $this->teamsaccounts = $teamsaccounts;
  }
  public function setErrorMessage($msg) {
    $this->sError = $msg;
    // Default to same error for crons
    $this->sCronError = $msg;
  }
  public function setCronMessage($msg) {
    // Used to overwrite any errors with a custom cron one
    $this->sCronError = $msg;
  }
  public function getError() {
    return $this->sError;
  }
  /**
   * Additional information in error string for cronjobs logging
   **/
  public function getCronError() {
    return $this->sCronError;
  }

  /**
   * Get error message from error code array
   * @param errCode string Error code string
   * @param optional string Optional addtitional error strings to append
   * @retrun string Error Message
   **/
  public function getErrorMsg($errCode='') {
    if (!is_array($this->aErrorCodes)) return 'Error codes not loaded';
    if (!array_key_exists($errCode, $this->aErrorCodes)) return 'Unknown Error Code: ' . $errCode;
    if (func_num_args() > 1) {
      $args = func_get_args();
      array_shift($args);
      $param_count = substr_count($this->aErrorCodes[$errCode], '%s');
      if ($param_count == count($args)) {
        return vsprintf($this->aErrorCodes[$errCode], $args);
      } else {
        return $this->aErrorCodes[$errCode] . ' (missing information to complete string)';
      }
    } else {
      return $this->aErrorCodes[$errCode];
    }
  }

  /**
   * Get an element as an associated array
   **/
  protected function getAllAssoc($value, $field='id', $type='i') {
    $this->debug->append("STA " . __METHOD__, 4);
    $stmt = $this->mysqli->prepare("SELECT * FROM $this->table WHERE $field = ? LIMIT 1");
    if ($this->checkStmt($stmt) && $stmt->bind_param($type, $value) && $stmt->execute() && $result = $stmt->get_result())
      return $result->fetch_assoc();
    return false;
  }

  /**
   * Get a single row from the table
   * @param value string Value to search for
   * @param search Return column to search for
   * @param field string Search column
   * @param type string Type of value
   * @param lower bool try with LOWER comparision
   * @return array Return result
   **/
  protected function getSingle($value, $search='id', $field='id', $type="i", $lower=false) {
    $this->debug->append("STA " . __METHOD__, 4); 
    $sql = "SELECT $search FROM $this->table WHERE";
    $lower ? $sql .= " LOWER($field) = LOWER(?)" : $sql .= " $field = ?";
    $sql .= " LIMIT 1";
    $stmt = $this->mysqli->prepare($sql);
    if ($this->checkStmt($stmt)) {
      $stmt->bind_param($type, $value);
      $stmt->execute();
      $stmt->bind_result($retval);
      $stmt->fetch();
      $stmt->close();
      return $retval;
      return false;
    }
  }

  /**
   * Catch SQL errors with this method
   * @param error_code string Error code to read
   **/
  protected function sqlError($error_code='E0020') {
    // More human-readable error for UI
    if (func_num_args() == 0) {
      $this->setErrorMessage($this->getErrorMsg($error_code));
    } else {
      $this->setErrorMessage(call_user_func_array(array($this, 'getErrorMsg'), func_get_args()));
    }
    // Default to SQL error for debug and cron errors
    $this->debug->append($this->getErrorMsg('E0019', $this->mysqli->error));
    $this->setCronMessage($this->getErrorMsg('E0019', $this->mysqli->error));
    return false;
  }

  /**
   * @param userID int Account ID
   * Update a single row in a table
   * @param field string Field to update
   * @return bool
   **/
  protected function updateSingle($id, $field) {
    $this->debug->append("STA " . __METHOD__, 4); 
    $stmt = $this->mysqli->prepare("UPDATE $this->table SET `" . $field['name'] . "` = ? WHERE id = ? LIMIT 1");
    if ($this->checkStmt($stmt) && $stmt->bind_param($field['type'].'i', $field['value'], $id) && $stmt->execute())
      return true;
    $this->debug->append("Unable to update " . $field['name'] . " with " . $field['value'] . " for ID $id");
    $this->sqlError();
  }

  /**
   * We may need to generate our bind_param list
   **/
  public function addParam($type, &$value) {
    $this->values[] = $value;
    $this->types .= $type;
  }
  public function getParam() {
    $array = array_merge(array($this->types), $this->values);
    // Clear the data
    $this->values = NULL;
    $this->types = NULL;
    // See here why we need this: http://stackoverflow.com/questions/16120822/mysqli-bind-param-expected-to-be-a-reference-value-given
    if (strnatcmp(phpversion(),'5.3') >= 0) {
      $refs = array();
      foreach($array as $key => $value)
        $refs[$key] = &$array[$key];
      return $refs;
    }
    return $array;
  }

  // Some basic getters that help in child classes
  public function getName($id) {
    $this->debug->append("STA " . __METHOD__, 4);
    return $this->getSingle($id, 'name', 'id', 'i');
  }
  public function getRowCount() {
    $stmt = $this->mysqli->prepare("SELECT COUNT(id) AS count FROM $this->table");
    if ($this->checkStmt($stmt) && $stmt->execute() && $result = $stmt->get_result())
      return $result->fetch_object()->count;
    return false;
  }
  public function getMaxId() {
    $stmt = $this->mysqli->prepare("SELECT MAX(id) id FROM $this->table");
    if ($this->checkStmt($stmt) && $stmt->execute() && $result = $stmt->get_result())
      return $result->fetch_object()->id;
    return false;
  }
  public function getMinId() {
    $stmt = $this->mysqli->prepare("SELECT MIN(id) id FROM $this->table");
    if ($this->checkStmt($stmt) && $stmt->execute() && $result = $stmt->get_result())
      return $result->fetch_object()->id;
    return false;
  }
  public function checkStmt($bState) {
    $this->debug->append("STA " . __METHOD__, 4);
    if ($bState ===! true) {
      $this->debug->append("Failed to prepare statement: " . $this->mysqli->error);
      $this->setErrorMessage('Internal application Error');
      return false;
    }
    return true;
  }
}
?>
