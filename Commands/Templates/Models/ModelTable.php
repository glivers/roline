<?php

class %sTable {

	/**
	*@tablename
	*/
	public $table = '';

	/**
	 *@column
	 *@primary
	 *@type autonumber
	 */
	protected $id;

	/**
	 *@column
	 *@type datetime
	 */
	protected $date_created;

	/**
	 *@column
	 *@type datetime
	 */
	protected $date_modified;

	/**
	 *@var string The query used to generate this model table
	 */
	protected $query_string;

}