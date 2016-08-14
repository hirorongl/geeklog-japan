<?php

/* Reminder: always indent with 4 spaces (no tabs). */
// +---------------------------------------------------------------------------+
// | Geeklog 2.1                                                               |
// +---------------------------------------------------------------------------+
// | pgsql.class.php                                                           |
// |                                                                           |
// | PostgreSQL database class                                                 |
// +---------------------------------------------------------------------------+
// | Copyright (C) 2000-2011 by the following authors:                         |
// |                                                                           |
// | Authors: Stanislav Palatnik, spalatnikk AT gmail DoT com                  |
// +---------------------------------------------------------------------------+
// |                                                                           |
// | This program is free software; you can redistribute it and/or             |
// | modify it under the terms of the GNU General Public License               |
// | as published by the Free Software Foundation; either version 2            |
// | of the License, or (at your option) any later version.                    |
// |                                                                           |
// | This program is distributed in the hope that it will be useful,           |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
// | GNU General Public License for more details.                              |
// |                                                                           |
// | You should have received a copy of the GNU General Public License         |
// | along with this program; if not, write to the Free Software Foundation,   |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.           |
// |                                                                           |
// +---------------------------------------------------------------------------+

/**
 * This file is the pgsql implementation of the Geeklog abstraction layer.
 * Unfortunately the Geeklog abstraction layer isn't 100% abstract because a few
 * key functions use MySQL's REPLACE INTO syntax which is not a SQL standard.
 * This issue will need to be resolved some time ...
 */
class Database
{
    // PRIVATE PROPERTIES

    /**
     * @var string
     */
    private $_host = '';

    /**
     * @var string
     */
    private $_name = '';

    /**
     * @var string
     */
    private $_user = '';

    /**
     * @var string
     */
    private $_pass = '';

    /**
     * @var resource|false
     */
    private $_db = false;

    /**
     * @var bool
     */
    private $_verbose = false;

    /**
     * @var bool
     */
    private $_display_error = false;

    /**
     * @var callable
     */
    private $_errorlog_fn = '';

    /**
     * @var string
     */
    private $_charset = '';

    /**
     * @var int
     */
    private $_pgsql_version = 0;

    // PRIVATE METHODS

    /**
     * Logs messages
     * Logs messages by calling the function held in $_errorlog_fn
     *
     * @param    string $msg Message to log
     */
    private function _errorlog($msg)
    {
        $function = $this->_errorlog_fn;

        if (function_exists($function)) {
            $function($msg);
        }
    }

    /**
     * Creates a connection string for pg_connect
     * Doesn't show the port because it isn't being provided in class default assumed
     */
    private function buildString()
    {
        $conn_string = '';
        $conn_string .= (!empty($this->_host)) ? 'host=' . $this->_host : 'localhost';
        $conn_string .= (!empty($this->_name)) ? ' dbname=' . $this->_name : '';
        $conn_string .= (!empty($this->_user)) ? ' user=' . $this->_user : '';
        $conn_string .= (!empty($this->_pass)) ? ' password=' . $this->_pass : '';

        return $conn_string;
    }

    /**
     * Return if a given table exists in the current database
     *
     * @param  string $tableName
     * @param  int    $ignoreErrors
     * @return bool
     */
    public function dbTableExists($tableName, $ignoreErrors = 0)
    {
        global $_TABLES;

        $result = $this->dbQuery("SELECT check_table('{$_TABLES[$tableName]}', 'public');", $ignoreErrors);
        $row = $this->dbFetchArray($result);
        $retval = !empty($row['check_table']);

        return $retval;
    }

    /**
     * Connects to the pgSQL database server
     * This function connects to the PostgreSQL server and returns the connection object
     *
     * @return   object      Returns connection object
     */
    private function _connect()
    {
        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Inside database->_connect ***");
        }

        // Connect to pgSQL server
        $this->_db = pg_connect($this->buildString()) or die('Cannot connect to DB server');

        if ($this->_pgsql_version == 0) {
            $v = pg_version($this->_db);
            $this->_pgsql_version = $v['client'];
        }

        if (!($this->_db)) {
            if (pg_connection_busy($this->_db)) {
                if ($this->isVerbose()) {
                    $this->_errorlog("\n*** The current connection is busy ***");
                }
            } else {
                if ($this->isVerbose()) {
                    $this->_errorlog("\n*** Error in database->_connect ***");
                }
            }
        }

        if ($this->_pgsql_version >= 7.4 && $this->_charset == 'utf-8') {
            pg_query($this->_db, "SET NAMES 'UTF8'");
        }

        if ($this->isVerbose()) {
            $this->_errorlog("\n***leaving database->_connect***");
        }
    }


    // PUBLIC METHODS

    /**
     * constructor for database
     * This initializes an instance of the database object
     *
     * @param        string $dbhost     Database host
     * @param        string $dbname     Name of database
     * @param        string $dbuser     User to make connection as
     * @param        string $dbpass     Password for dbuser
     * @param        string $errorlogfn Name of the errorlog function
     * @param        string $charset    character set to use
     */
    public function __construct($dbhost, $dbname, $dbuser, $dbpass, $errorlogfn = '', $charset = '')
    {
        $this->_host = $dbhost;
        $this->_name = $dbname;
        $this->_user = $dbuser;
        $this->_pass = $dbpass;
        $this->_verbose = false;
        $this->_errorlog_fn = $errorlogfn;
        $this->_charset = strtolower($charset);
        $this->_pgsql_version = 0;

        $this->_connect();
    }

    /**
     * Retrieves returns the number of effected rows for last query
     * Retrieves returns the number of effected rows for last query
     *
     * @param    object $recordset The recordset to operate on
     * @return   int     Number of rows affected by last query
     */
    public function dbAffectedRows($recordset)
    {
        if (!isset($recordset)) {
            $recordset = pg_get_result($this->_db);
        }

        return @pg_affected_rows($recordset);
    }

    /**
     * Returns the contents of one cell from a PostgreSQL result set
     *
     * @param    resource $recordset The recordset to operate on
     * @param    int      $row       row to get data from
     * @param    mixed    $field     field to return
     * @return   mixed    depends on field content
     */
    public function dbResult($recordset, $row, $field = 0)
    {
        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Inside database->dbResult ***");

            if (empty($recordset)) {
                $this->_errorlog("\n*** Passed recordset isn't valid ***");
            } else {
                $this->_errorlog("\n*** Everything looks good ***");
            }

            $this->_errorlog("\n*** Leaving database->dbResult ***");
        }

        return @pg_fetch_result($recordset, $row, $field);
    }

    /**
     * Retrieves record from a recordset
     * Gets the next record in a recordset and returns in array
     *
     * @param    resource $recordSet The record set to operate on
     * @param    boolean  $both      get both assoc and numeric indices
     * @return   array     Returns data array of current row from record set
     */
    public function dbFetchArray($recordSet, $both = false)
    {
        $resultType = $both ? PGSQL_BOTH : PGSQL_ASSOC;

        return pg_fetch_array($recordSet, null, $resultType);
    }

    /**
     * Retrieves returns the field name for a field
     * Returns the field name for a given field number
     *
     * @param    resource $recordSet   The recordset to operate on
     * @param    int      $fieldNumber field number to return the name of
     * @return   string   Returns name of specified field
     */
    function dbFieldName($recordSet, $fieldNumber)
    {
        return @pg_field_name($recordSet, $fieldNumber);
    }

    /**
     * Copies a record from one table to another (can be the same table)
     * This will use a REPLACE INTO...SELECT FROM to copy a record from one table
     * to another table.  They can be the same table.
     *
     * @param    string       $table     Table to insert record into
     * @param    string       $fields    Comma delmited list of fields to copy over
     * @param    string       $values    Values to store in database fields
     * @param    string       $tableFrom Table to get record from
     * @param    array|string $id        field name(s) to use in where clause
     * @param    array|string $value     Value(s) to use in where clause
     * @return   boolean     Returns true on success otherwise false
     */
    public function dbCopy($table, $fields, $values, $tableFrom, $id, $value)
    {
        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Inside database->dbCopy ***");
        }

        $sql = "INSERT INTO $table ($fields) SELECT $values FROM $tableFrom";

        if (is_array($id) || is_array($value)) {
            $num_ids = count($id);

            if (is_array($id) && is_array($value) && $num_ids == count($value)) {
                // they are arrays, traverse them and build sql
                $sql .= ' WHERE ';
                for ($i = 1; $i <= $num_ids; $i++) {
                    if ($i == $num_ids) {
                        $sql .= current($id) . " = '" . current($value) . "'";
                    } else {
                        $sql .= current($id) . " = '" . current($value) . "' AND ";
                    }
                    next($id);
                    next($value);
                }
            } else {
                // error, they both have to be arrays and of the
                // same size
                return false;
            }
        } else {
            if (!empty($id) && (isset($value) || $value != "")) {
                $sql .= " WHERE $id = '$value'";
            }
        }

        $this->dbQuery($sql);
        $this->dbDelete($tableFrom, $id, $value);

        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Leaving database->dbCopy ***");
        }
    }

    /**
     * Executes a query on the pgSQL server
     * This executes the passed SQL and returns the recordset or errors out
     *
     * @param    string $sql           SQL to be executed
     * @param    int    $ignore_errors If 1 this function suppresses any error messages
     * @return   bool|resource          Returns results of query
     */
    public function dbQuery($sql, $ignore_errors = 0)
    {
        if ($this->isVerbose()) {
            $this->_errorlog("\n***inside database->dbQuery***");
            $this->_errorlog("\n*** sql to execute is $sql ***");
        }

        // Replace some non ANSI keywords
        if (preg_match('#LIMIT ([0-9]+),([\\s])?([0-9]+)#', $sql, $matches)) {
            $sql = str_replace($matches[0], 'LIMIT ' . $matches[3] . ' OFFSET ' . $matches[1], $sql);
        }

        // Run query
        if ($ignore_errors) {
            $result = pg_query($this->_db, $sql);
        } else {
            $result = pg_query($this->_db, $sql);

            if ($result === false) {
                trigger_error($this->dbError($sql), E_USER_ERROR);
            }
        }

        // If OK, return otherwise echo error
        if (pg_result_error($result) == false && !empty($result)) {
            if ($this->isVerbose()) {
                $this->_errorlog("\n***sql ran just fine***");
                $this->_errorlog("\n*** Leaving database->dbQuery ***");
            }

            return $result;
        } else {
            // callee may want to supress printing of errors
            if ($ignore_errors) {
                return false;
            }

            if ($this->isVerbose()) {
                $this->_errorlog("\n***sql caused an error***");
                $this->_errorlog("\n*** Leaving database->dbQuery ***");
            }
        }
    }

    /**
     * Saves information to the database
     * This will use a REPLACE INTO to save a record into the
     * database
     *
     * @param    string $table  The table to save to
     * @param    string $fields string  Comma-delimited list of fields to save
     * @param    string $values Values to save to the database table
     */
    public function dbSave($table, $fields, $values)
    {
        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Inside database->dbSave ***");
        }

        $sql = "SELECT COUNT(*) FROM $table";
        $result = $this->dbQuery($sql);
        $row = pg_fetch_row($result);

        if ($row[0] == 0) {
            // nothing in the table yet
            $sql = "INSERT INTO $table($fields) VALUES($values)";
        } else {
            unset($row);
            unset($result);
            $fields_array = explode(',', $fields);
            $values_array = DBINT_parseCsvSqlString($values);
            $values = str_replace('0000-00-00 00:00:00', 'NOW()', $values);
            $row = array();
            $sql = 'SELECT pg_attribute.attname FROM pg_index, pg_class, pg_attribute
                    WHERE pg_class.oid = \'' . $table . '\'::regclass AND
                    indrelid = pg_class.oid AND
                    pg_attribute.attrelid = pg_class.oid AND
                    pg_attribute.attnum = any(pg_index.indkey)
                    GROUP BY pg_attribute.attname, pg_attribute.attnum;';
            $result = $this->dbQuery($sql);

            while ($fetched = pg_fetch_row($result)) {
                $row[] = $fetched;
            }

            $counter = count($row);

            if (!empty($row[0])) {
                $key = array_search($row[0][0], $fields_array);

                if ($key !== false) {
                    $sql = "DELETE FROM $table WHERE ";
                    $uniqueNo = count($row);

                    for ($i = 0; $i < $uniqueNo; $i++) {
                        $key = array_search($row[$i][0], $fields_array);

                        if ($key !== false) {
                            // $fields contains the primary key already
                            $validKey = false;
                            if (isset($values_array[$key][0]) &&
                                $values_array[$key] != 'UNIX_TIMESTAMP()' &&
                                $values_array[$key] != '0000-00-00 00:00:00'
                            ) {
                                if ($values_array[$key][0] == "'") {
                                    if ((substr($sql, -5) != ' AND ') &&
                                        (substr($sql, -6) != 'WHERE ')
                                    ) {
                                        $sql .= ' AND ';
                                    }

                                    $sql .= "{$row[$i][0]}={$values_array[$key]}";
                                } else {
                                    if (substr($sql, -5) != ' AND ' &&
                                        (substr($sql, -6) != 'WHERE ')
                                    ) {
                                        $sql .= ' AND ';
                                    }
                                    $sql .= "{$row[$i][0]}='{$values_array[$key]}'";
                                }
                            }
                        }
                    }

                    if ($uniqueNo < 2) {
                        if (isset($values_array[$key + 1][0]) &&
                            $values_array[$key + 1] != 'UNIX_TIMESTAMP()' &&
                            $values_array[$key + 1] != '0000-00-00 00:00:00'
                        ) {
                            if ($values_array[$key + 1][0] == "'") {
                                if ((substr($sql, -5) != ' AND ') &&
                                    (substr($sql, -6) != 'WHERE ')
                                ) {
                                    $sql .= ' AND ';
                                }
                                $sql .= "{$fields_array[$key+1]}={$values_array[$key+1]}";
                            } else {
                                if ((substr($sql, -5) != ' AND ') &&
                                    (substr($sql, -6) != 'WHERE ')
                                ) {
                                    $sql .= ' AND ';
                                }
                                $sql .= "{$fields_array[$key+1]}='{$values_array[$key+1]}'";
                            }
                        }
                    }

                    $this->dbQuery($sql);
                    $sql = "INSERT INTO $table ($fields) VALUES ($values)";
                } elseif ($counter > 1) {
                    // we will search for unique fields and see if they are getting duplicates
                    $where_clause = '';

                    for ($x = 1; $x < $counter; $x++) {
                        $key = array_search($row[$x][0], $fields_array);
                        if ($key !== false) {
                            if (!empty($where_clause)) {
                                $where_clause .= ' AND ';
                            }
                            $values_array[$key] = str_replace('\'', '', $values_array[$key]);
                            $where_clause .= "{$row[$x][0]} ='{$values_array[$key]}'";
                        }
                    }

                    $result = $this->dbQuery($sql);
                    $row2 = pg_fetch_row($result);

                    if ($row2 && is_array($row2) && !empty($row2[0])) {
                        $sql = "DELETE FROM $table WHERE $where_clause";
                        $result = $this->dbQuery($sql);
                    }

                    $sql = "INSERT INTO $table ($fields) VALUES ($values)";
                } else {
                    $this->_errorlog("There was a problem saving this DB_save call: $fields, $values");
                }
            } else {
                // no keys to worry about
                $sql = "INSERT INTO $table ($fields) VALUES ($values)";
            }
        }
        $this->dbQuery($sql);

        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Leaving database->dbSave ***");
        }
    }

    /**
     * Deletes data from the database
     * This will delete some data from the given table where id = value.  If
     * id and value are arrays then it will traverse the arrays setting
     * $id[curval] = $value[curval].
     *
     * @param    string       $table Table to delete data from
     * @param    array|string $id    field name(s) to include in where clause
     * @param    array|string $value field value(s) corresponding to field names
     * @return   boolean     Returns true on success otherwise false
     */
    public function dbDelete($table, $id, $value)
    {
        if ($this->isVerbose()) {
            $this->_errorlog("\n*** inside database->dbDelete ***");
        }

        $sql = "DELETE FROM $table";

        if (is_array($id) || is_array($value)) {
            $num_ids = count($id);

            if (is_array($id) && is_array($value) && $num_ids == count($value)) {
                // they are arrays, traverse them and build sql
                $sql .= ' WHERE ';
                for ($i = 1; $i <= $num_ids; $i++) {
                    if ($i == $num_ids) {
                        if ($value[0] == "'") {
                            $sql .= current($id) . " = " . current($value);
                        } else {
                            $sql .= current($id) . " = '" . current($value) . "'";
                        }
                    } else {
                        if ($value[0] == "'") {
                            $sql .= current($id) . " = " . current($value) . " AND ";
                        } else {
                            $sql .= current($id) . " = '" . current($value) . "' AND ";
                        }
                    }
                    next($id);
                    next($value);
                }
            } else {
                // error, they both have to be arrays and of the same size
                $this->_errorlog("The columns ($id) do not match the value count ($value)");

                return false;
            }
        } else {
            // just regular string values, build sql
            if (!empty($id) && (isset($value) || $value != "")) {
                $sql .= " WHERE $id = '$value'";
            }
        }

        $this->dbQuery($sql);

        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Leaving database->dbDelete ***");
        }

        return true;
    }

    /**
     * Changes records in a table
     * This will change the data in the given table that meet the given criteria and will
     * redirect user to another page if told to do so
     *
     * @param    string       $table          Table to perform change on
     * @param    string       $item_to_set    field name of unique ID field for table
     * @param    string       $value_to_set   Value for id
     * @param    array|string $id             additional field name used in where clause
     * @param    array|string $value          additional values used in where clause
     * @param    boolean      $supress_quotes if false it will not use '<value>' in where clause
     * @return   boolean     Returns true on success otherwise false
     */
    public function dbChange($table, $item_to_set, $value_to_set, $id, $value, $supress_quotes = false)
    {
        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Inside dbChange ***");
        }

        if ($supress_quotes) {
            $sql = "UPDATE $table SET $item_to_set = $value_to_set";
        } else {
            $sql = "UPDATE $table SET $item_to_set = '$value_to_set'";
        }

        if (is_array($id) || is_array($value)) {
            $num_ids = count($id);

            if (is_array($id) && is_array($value) && $num_ids == count($value)) {
                // they are arrays, traverse them and build sql
                $sql .= ' WHERE ';

                for ($i = 1; $i <= $num_ids; $i++) {
                    if ($i == $num_ids) {
                        $sql .= current($id) . " = '" . current($value) . "'";
                    } else {
                        $sql .= current($id) . " = '" . current($value) . "' AND ";
                    }
                    next($id);
                    next($value);
                }
            } else {
                // error, they both have to be arrays and of the
                // same size
                return false;
            }
        } else {
            // These are regular strings, build sql
            if (!empty($id) && (isset($value) || $value != "")) {
                $sql .= " WHERE $id = '$value'";
            }
        }

        if ($this->isVerbose()) {
            $this->_errorlog("dbChange sql = $sql");
        }

        $this->dbQuery($sql);

        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Leaving database->dbChange ***");
        }
    }

    /**
     * Retrieves the number of rows in a recordset
     * This returns the number of rows in a recordset
     *
     * @param    resource $recordSet The recordset to operate one
     * @return   int         Returns number of rows otherwise false (0)
     */
    public function dbNumRows($recordSet)
    {
        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Inside database->dbNumRows ***");
        }

        // return only if recordset exists, otherwise 0
        if ($recordSet) {
            $rows = pg_num_rows($recordSet);

            if ($this->isVerbose()) {
                $this->_errorlog('got ' . $rows . ' rows');
                $this->_errorlog("\n*** Inside database->dbNumRows ***");
            }

            return $rows;
        } else {
            if ($this->isVerbose()) {
                $this->_errorlog("got no rows");
                $this->_errorlog("\n*** Inside database->dbNumRows ***");
            }

            return 0;
        }
    }

    /**
     * Returns the number of records for a query that meets the given criteria
     * This will build a SELECT count(*) statement with the given criteria and
     * return the result
     *
     * @param    string       $table Table to perform count on
     * @param    array|string $id    field name(s) of fields to use in where clause
     * @param    array|string $value Value(s) to use in where clause
     * @return   boolean     returns count on success otherwise false
     */
    public function dbCount($table, $id = '', $value = '')
    {
        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Inside database->dbCount ***");
        }

        $sql = "SELECT COUNT(*) FROM $table";

        if (is_array($id) || is_array($value)) {
            $num_ids = count($id);

            if (is_array($id) && is_array($value) && $num_ids == count($value)) {
                // they are arrays, traverse them and build sql
                $sql .= ' WHERE ';
                for ($i = 1; $i <= $num_ids; $i++) {
                    if ($i == $num_ids) {
                        $sql .= current($id) . " = '" . current($value) . "'";
                    } else {
                        $sql .= current($id) . " = '" . current($value) . "' AND ";
                    }
                    next($id);
                    next($value);
                }
            } else {
                // error, they both have to be arrays and of the
                // same size
                return false;
            }
        } else {
            if (!empty($id) && (isset($value) || $value != "")) {
                $sql .= " WHERE $id = '$value'";
            }
        }

        if ($this->isVerbose()) {
            $this->_errorlog("\n*** sql = $sql ***");
        }

        $result = $this->dbQuery($sql);

        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Leaving database->dbCount ***");
        }

        return ($this->dbResult($result, 0));
    }

    /**
     * Returns the last ID inserted
     * Returns the last auto_increment ID generated
     *
     * @param    resource $link_identifier identifier for opened link
     * @param    string   $sequence        sequence name the sequence to get the value last insert ID from
     * @return   int      Returns last auto-generated ID
     */
    function dbInsertId($link_identifier = null, $sequence = '')
    {
        if (!empty($sequence)) {
            $result = @pg_query('SELECT CURRVAL(\'' . $sequence . '\'); ');
            if ($result === false) {
                $result = @pg_query('SELECT NEXTVAL(\'' . $sequence . '\'); ');
            }
        } else {
            $result = pg_query('SELECT LASTVAL();');
        }

        $row = pg_fetch_row($result);
        unset($result);

        return $row[0];
    }

    /**
     * Lock a table
     * Locks a table for write operations
     *
     * @param  string $table Table to lock
     * @see dbUnlockTable
     */
    public function dbLockTable($table)
    {
        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Inside database->dbLockTable ***");
        }

        $sql = "START Transaction";

        $this->dbQuery($sql);

        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Leaving database->dbLockTable ***");
        }
    }

    /**
     * Unlock a table
     * Unlocks a table after a dbLockTable (actually, unlocks all tables)
     *
     * @param    string $table Table to unlock (ignored)
     * @see dbLockTable
     */
    public function dbUnlockTable($table)
    {
        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Inside database->dbUnlockTable ***");
        }

        $sql = 'COMMIT Transaction';

        $this->dbQuery($sql);

        if ($this->isVerbose()) {
            $this->_errorlog("\n*** Leaving database->dbUnlockTable ***");
        }
    }

    /**
     * Turns debug mode on
     * Set this to true to see debug messages
     *
     * @param    bool $flag true or false
     */
    public function setVerbose($flag)
    {
        $this->_verbose = (bool) $flag;
    }

    /**
     * Turns detailed error reporting on
     * If set to true, this will display detailed error messages on the site.
     * Otherwise, it will only that state an error occurred without going into
     * details. The complete error message (including the offending SQL request)
     * is always available from error.log.
     *
     * @param    bool $flag true or false
     */
    public function setDisplayError($flag)
    {
        $this->_display_error = (bool) $flag;
    }

    /**
     * Returns a database error message
     *
     * @param    string $sql SQL that may have caused the error
     * @return   string      Text for error message
     */
    public function dbError($sql = '')
    {
        $fn = '';
        $btr = debug_backtrace();

        if (!empty($btr)) {
            for ($i = 0; $i < count($btr); $i++) {
                if (isset($btr[$i])) {
                    $b = $btr[$i];

                    if ($b['function'] === 'DB_query') {
                        if (!empty($b['file']) && !empty($b['line'])) {
                            $fn = $b['file'] . ':' . $b['line'];
                        }
                        break;
                    }
                } else {
                    break;
                }
            }

            if (!empty($fn)) {
                $fn = ' in ' . $fn;
            }
        }

        $result = pg_get_result($this->_db);

        if ($this->_pgsql_version >= 7.4) {
            // this provides a much more detailed error report
            if (pg_result_error_field($result, PGSQL_DIAG_SOURCE_LINE)) {
                $this->_errorlog('You have an error in your SQL query on line '
                    . pg_result_error_field($result, PGSQL_DIAG_SOURCE_LINE)
                    . "$fn\nSQL in question: $sql");
                $this->_errorlog('Error: '
                    . pg_result_error_field($result, PGSQL_DIAG_SQLSTATE)
                    . "$fn\nDescription: "
                    . pg_result_error_field($result, PGSQL_DIAG_MESSAGE_DETAIL));

                if ($this->_display_error) {
                    $error = "An SQL error has occurred in the following SQL: $sql";
                } else {
                    $error = 'An SQL error has occurred. Please see error.log for details.';
                }

                return $error;
            }
        } else {
            if (pg_result_error($result)) {
                $this->_errorlog(pg_result_error($result)
                    . "$fn. SQL in question: $sql");

                if ($this->_display_error) {
                    $error = 'Error ' . pg_result_error($result);
                } else {
                    $error = 'An SQL error has occurred. Please see error.log for details.';
                }

                return $error;
            }
        }

        return '';
    }

    /**
     * Checks to see if debug mode is on
     * Returns value of $_verbose
     *
     * @return   bool     true if in verbose mode otherwise false
     */
    public function isVerbose()
    {
        if ($this->_verbose && (empty($this->_errorlog_fn) || !function_exists($this->_errorlog_fn))) {
            echo "\n<br" . XHTML . "><b>Can't run pgsql.class.php verbosely because the errorlog "
                . "function wasn't set or doesn't exist</b><br" . XHTML . ">\n";

            return false;
        }

        return $this->_verbose;
    }

    /**
     * @return     string     the version of the database application
     */
    public function dbGetVersion()
    {
        $v = @pg_version($this->_db);

        return $v['server'];
    }

    /**
     * Escapes a string so that it can be safely used in a query
     *
     * @param   string $str a string to be escaped
     * @return  string
     */
    public function dbEscapeString($str)
    {
        $retval = pg_escape_string($this->_db, $str);

        return $retval;
    }
}
