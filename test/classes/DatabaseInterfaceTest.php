<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Test for faked database access
 *
 * @package PhpMyAdmin-test
 */
declare(strict_types=1);

namespace PhpMyAdmin\Tests;

use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Dbi\DbiDummy;
use PhpMyAdmin\Tests\PmaTestCase;
use PhpMyAdmin\Util;
use ReflectionClass;

/**
 * Tests basic functionality of dummy dbi driver
 *
 * @package PhpMyAdmin-test
 */
class DatabaseInterfaceTest extends PmaTestCase
{
    /**
     * @var DatabaseInterface
     */
    private $_dbi;

    /**
     * Configures test parameters.
     *
     * @return void
     */
    protected function setUp()
    {
        $GLOBALS['server'] = 0;
        $extension = new DbiDummy();
        $this->_dbi = new DatabaseInterface($extension);
    }

    /**
     * Get private method by setting visibility to public.
     *
     * @param string $name method name
     * @param array $params parameters for the invocation
     *
     * @return mixed the output from the private method.
     * @throws \ReflectionException
     */
    private function getPrivateMethod($name)
    {
        $class = new ReflectionClass(DatabaseInterface::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Tests for DBI::getCurrentUser() method.
     *
     * @param array  $value    value
     * @param string $string   string
     * @param array  $expected expected result
     *
     * @return void
     * @test
     * @dataProvider currentUserData
     */
    public function testGetCurrentUser($value, $string, $expected)
    {
        Util::cacheUnset('mysql_cur_user');

        $extension = new DbiDummy();
        $extension->setResult('SELECT CURRENT_USER();', $value);

        $dbi = new DatabaseInterface($extension);

        $this->assertEquals(
            $expected,
            $dbi->getCurrentUserAndHost()
        );

        $this->assertEquals(
            $string,
            $dbi->getCurrentUser()
        );
    }

    /**
     * Data provider for getCurrentUser() tests.
     *
     * @return array
     */
    public function currentUserData()
    {
        return [
            [[['pma@localhost']], 'pma@localhost', ['pma', 'localhost']],
            [[['@localhost']], '@localhost', ['', 'localhost']],
            [false, '@', ['', '']],
        ];
    }

    /**
     * Tests for DBI::getColumnMapFromSql() method.
     *
     * @return void
     * @test
     */
    public function testPMAGetColumnMap()
    {
        $extension = $this->getMockBuilder('PhpMyAdmin\Dbi\DbiDummy')
            ->disableOriginalConstructor()
            ->getMock();

        $extension->expects($this->any())
            ->method('realQuery')
            ->will($this->returnValue(true));

        $meta1 = new \stdClass();
        $meta1->table = "meta1_table";
        $meta1->name = "meta1_name";

        $meta2 = new \stdClass();
        $meta2->table = "meta2_table";
        $meta2->name = "meta2_name";

        $extension->expects($this->any())
            ->method('getFieldsMeta')
            ->will(
                $this->returnValue(
                    [
                        $meta1, $meta2
                    ]
                )
            );

        $dbi = new DatabaseInterface($extension);

        $sql_query = "PMA_sql_query";
        $view_columns = [
            "view_columns1", "view_columns2"
        ];

        $column_map = $dbi->getColumnMapFromSql(
            $sql_query,
            $view_columns
        );

        $this->assertEquals(
            [
                'table_name' => 'meta1_table',
                'refering_column' => 'meta1_name',
                'real_column' => 'view_columns1'
            ],
            $column_map[0]
        );
        $this->assertEquals(
            [
                'table_name' => 'meta2_table',
                'refering_column' => 'meta2_name',
                'real_column' => 'view_columns2'
            ],
            $column_map[1]
        );
    }

    /**
     * Tests for DBI::getSystemDatabase() method.
     *
     * @return void
     * @test
     */
    public function testGetSystemDatabase()
    {
        $sd = $this->_dbi->getSystemDatabase();
        $this->assertInstanceOf('PhpMyAdmin\SystemDatabase', $sd);
    }

    /**
     * Tests for DBI::postConnectControl() method.
     *
     * @return void
     * @test
     */
    public function testPostConnectControl()
    {
        $GLOBALS['db'] = '';
        $GLOBALS['cfg']['Server']['only_db'] = [];
        $this->_dbi->postConnectControl();
        $this->assertInstanceOf('PhpMyAdmin\Database\DatabaseList', $GLOBALS['dblist']);
    }

    /**
     * Test for getDbCollation
     *
     * @return void
     * @test
     */
    public function testGetDbCollation()
    {
        $GLOBALS['server'] = 1;
        // test case for system schema
        $this->assertEquals(
            'utf8_general_ci',
            $this->_dbi->getDbCollation("information_schema")
        );

        $GLOBALS['cfg']['Server']['DisableIS'] = false;
        $GLOBALS['cfg']['DBG']['sql'] = false;

        $this->assertEquals(
            'utf8_general_ci',
            $this->_dbi->getDbCollation('pma_test')
        );
    }

    /**
     * Test for getServerCollation
     *
     * @return void
     * @test
     */
    public function testGetServerCollation()
    {
        $GLOBALS['server'] = 1;
        $GLOBALS['cfg']['DBG']['sql'] = true;
        $this->assertEquals('utf8_general_ci', $this->_dbi->getServerCollation());
    }

    /**
     * Test for getConnectionParams
     *
     * @param array      $server_cfg Server configuration
     * @param integer    $mode       Mode to test
     * @param array|null $server     Server array to test
     * @param array      $expected   Expected result
     *
     * @return void
     *
     * @dataProvider connectionParams
     */
    public function testGetConnectionParams($server_cfg, $mode, $server, $expected)
    {
        $GLOBALS['cfg']['Server'] = $server_cfg;
        $result = $this->_dbi->getConnectionParams($mode, $server);
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for getConnectionParams test
     *
     * @return array
     */
    public function connectionParams()
    {
        $cfg_basic = [
            'user' => 'u',
            'password' => 'pass',
            'host' => '',
            'controluser' => 'u2',
            'controlpass' => 'p2',
        ];
        $cfg_ssl = [
            'user' => 'u',
            'password' => 'pass',
            'host' => '',
            'ssl' => true,
            'controluser' => 'u2',
            'controlpass' => 'p2',
        ];
        $cfg_control_ssl = [
            'user' => 'u',
            'password' => 'pass',
            'host' => '',
            'control_ssl' => true,
            'controluser' => 'u2',
            'controlpass' => 'p2',
        ];
        return [
            [
                $cfg_basic,
                DatabaseInterface::CONNECT_USER,
                null,
                [
                    'u',
                    'pass',
                    [
                        'user' => 'u',
                        'password' => 'pass',
                        'host' => 'localhost',
                        'socket' => null,
                        'port' => 0,
                        'ssl' => false,
                        'compress' => false,
                        'controluser' => 'u2',
                        'controlpass' => 'p2',
                    ]
                ],
            ],
            [
                $cfg_basic,
                DatabaseInterface::CONNECT_CONTROL,
                null,
                [
                    'u2',
                    'p2',
                    [
                        'host' => 'localhost',
                        'socket' => null,
                        'port' => 0,
                        'ssl' => false,
                        'compress' => false,
                    ]
                ],
            ],
            [
                $cfg_ssl,
                DatabaseInterface::CONNECT_USER,
                null,
                [
                    'u',
                    'pass',
                    [
                        'user' => 'u',
                        'password' => 'pass',
                        'host' => 'localhost',
                        'socket' => null,
                        'port' => 0,
                        'ssl' => true,
                        'compress' => false,
                        'controluser' => 'u2',
                        'controlpass' => 'p2',
                    ]
                ],
            ],
            [
                $cfg_ssl,
                DatabaseInterface::CONNECT_CONTROL,
                null,
                [
                    'u2',
                    'p2',
                    [
                        'host' => 'localhost',
                        'socket' => null,
                        'port' => 0,
                        'ssl' => true,
                        'compress' => false,
                    ]
                ],
            ],
            [
                $cfg_control_ssl,
                DatabaseInterface::CONNECT_USER,
                null,
                [
                    'u',
                    'pass',
                    [
                        'user' => 'u',
                        'password' => 'pass',
                        'host' => 'localhost',
                        'socket' => null,
                        'port' => 0,
                        'ssl' => false,
                        'compress' => false,
                        'controluser' => 'u2',
                        'controlpass' => 'p2',
                        'control_ssl' => true,
                    ]
                ],
            ],
            [
                $cfg_control_ssl,
                DatabaseInterface::CONNECT_CONTROL,
                null,
                [
                    'u2',
                    'p2',
                    [
                        'host' => 'localhost',
                        'socket' => null,
                        'port' => 0,
                        'ssl' => true,
                        'compress' => false,
                    ]
                ],
            ],
        ];
    }

    /**
     * Test error formatting
     *
     * @param int    $error_number  Error code
     * @param string $error_message Error message as returned by server
     * @param string $match         Expected text
     *
     * @return void
     *
     * @dataProvider errorData
     */
    public function testFormatError($error_number, $error_message, $match)
    {
        $this->assertContains(
            $match,
            DatabaseInterface::formatError($error_number, $error_message)
        );
    }

    /**
     * @return array
     */
    public function errorData()
    {
        return [
            [2002, 'msg', 'The server is not responding'],
            [2003, 'msg', 'The server is not responding'],
            [1698, 'msg', 'logout.php'],
            [1005, 'msg', 'server_engines.php'],
            [1005, 'errno: 13', 'Please check privileges'],
            [-1, 'error message', 'error message'],
        ];
    }

    /**
     * Tests for DBI::isAmazonRds() method.
     *
     * @param mixed $value    value
     * @param mixed $expected expected result
     *
     * @return void
     * @test
     * @dataProvider isAmazonRdsData
     */
    public function atestIsAmazonRdsData($value, $expected)
    {
        Util::cacheUnset('is_amazon_rds');

        $extension = new DbiDummy();
        $extension->setResult('SELECT @@basedir', $value);

        $dbi = new DatabaseInterface($extension);

        $this->assertEquals(
            $expected,
            $dbi->isAmazonRds()
        );
    }

    /**
     * Data provider for isAmazonRds() tests.
     *
     * @return array
     */
    public function isAmazonRdsData()
    {
        return [
            [[['/usr']], false],
            [[['E:/mysql']], false],
            [[['/rdsdbbin/mysql/']], true],
            [[['/rdsdbbin/mysql-5.7.18/']], true],
        ];
    }

    /**
     * Test for version parsing
     *
     * @param string $version  version to parse
     * @param int    $expected expected numeric version
     * @param int    $major    expected major version
     * @param bool   $upgrade  whether upgrade should ne needed
     *
     * @return void
     *
     * @dataProvider versionData
     */
    public function testVersion($version, $expected, $major, $upgrade)
    {
        $ver_int = DatabaseInterface::versionToInt($version);
        $this->assertEquals($expected, $ver_int);
        $this->assertEquals($major, (int)($ver_int / 10000));
        $this->assertEquals($upgrade, $ver_int < $GLOBALS['cfg']['MysqlMinVersion']['internal']);
    }

    /**
     * @return array
     */
    public function versionData()
    {
        return [
            ['5.0.5', 50005, 5, true],
            ['5.05.01', 50501, 5, false],
            ['5.6.35', 50635, 5, false],
            ['10.1.22-MariaDB-', 100122, 10, false],
        ];
    }

    /**
     * Tests for DBI::setCollationl() method.
     *
     * @return void
     * @test
     */
    public function testSetCollation()
    {
        $extension = $this->getMockBuilder('PhpMyAdmin\Dbi\DbiDummy')
            ->disableOriginalConstructor()
            ->getMock();
        $extension->expects($this->any())->method('escapeString')
            ->will($this->returnArgument(1));

        $extension->expects($this->exactly(4))
            ->method('realQuery')
            ->withConsecutive(
                ["SET collation_connection = 'utf8_czech_ci';"],
                ["SET collation_connection = 'utf8mb4_bin_ci';"],
                ["SET collation_connection = 'utf8_czech_ci';"],
                ["SET collation_connection = 'utf8_bin_ci';"]
            )
            ->willReturnOnConsecutiveCalls(
                true,
                true,
                true,
                true
            );

        $dbi = new DatabaseInterface($extension);

        $GLOBALS['charset_connection'] = 'utf8mb4';
        $dbi->setCollation('utf8_czech_ci');
        $dbi->setCollation('utf8mb4_bin_ci');
        $GLOBALS['charset_connection'] = 'utf8';
        $dbi->setCollation('utf8_czech_ci');
        $dbi->setCollation('utf8mb4_bin_ci');
    }

    /**
     * Tests for DBI::getForeignKeyConstrains() method.
     *
     * @return void
     * @test
     */
    public function testGetForeignKeyConstrains()
    {
        $this->assertEquals([
            [
                'TABLE_NAME' => 'table2',
                'COLUMN_NAME' => 'idtable2',
                'REFERENCED_TABLE_NAME' => 'table1',
                'REFERENCED_COLUMN_NAME' => 'idtable1',
            ]
        ], $this->_dbi->getForeignKeyConstrains('test',['table1', 'table2']));
    }

    /**
     * Tests for DBI::getTablesFull() method.
     *
     * @return void
     * @test
     */
    public function testGetTablesFull()
    {
        $GLOBALS['cfg']['Server']['DisableIS'] = false;
        $GLOBALS['cfg']['MaxTableList'] = 2;
        $tables = $this->_dbi->getTablesFull(
            $database = 'test',
            $table = '',
            $tbl_is_group = false,
            $limit_offset = 0,
            $limit_count = true,
            $sort_by = 'Data_length',
            $sort_order = 'DESC',
            $table_type = null,
            $link = DatabaseInterface::CONNECT_USER);
        $this->assertEquals($tables, [
            'fks' => [
                'TABLE_CATALOG' => 'def',
                'TABLE_SCHEMA' => 'test',
                'TABLE_NAME' => 'fks',
                'TABLE_TYPE' => 'BASE TABLE',
                'ENGINE' => 'InnoDB',
                'VERSION' => '10',
                'ROW_FORMAT' => 'Dynamic',
                'TABLE_ROWS' => '0',
                'AVG_ROW_LENGTH' => '0',
                'DATA_LENGTH' => '16384',
                'MAX_DATA_LENGTH' => '0',
                'INDEX_LENGTH' => '16384',
                'DATA_FREE' => '0',
                'AUTO_INCREMENT' => '',
                'CREATE_TIME' => '11/7/2018 10:57',
                'UPDATE_TIME' => '',
                'CHECK_TIME' => '',
                'TABLE_COLLATION' => 'utf8mb4_0900_ai_ci',
                'CHECKSUM' => '',
                'CREATE_OPTIONS' => '',
                'TABLE_COMMENT' => '',
                'Db' => 'test',
                'Name' => 'fks',
                'Engine' => 'InnoDB',
                'Type' => 'InnoDB',
                'Version' => '10',
                'Row_format' => 'Dynamic',
                'Rows' => '0',
                'Avg_row_length' => '0',
                'Data_length' => '16384',
                'Max_data_length' => '0',
                'Index_length' => '16384',
                'Data_free' => '0',
                'Auto_increment' => '',
                'Create_time' => '11/7/2018 10:57',
                'Update_time' => '',
                'Check_time' => '',
                'Collation' => 'utf8mb4_0900_ai_ci',
                'Checksum' => '',
                'Create_options' => '',
                'Comment' => '',
            ],
            'table1' => [

                'TABLE_CATALOG'	=> 'def',
                'TABLE_SCHEMA'	=> 'test',
                'TABLE_NAME'	=> 'table1',
                'TABLE_TYPE'	=> 'BASE TABLE',
                'ENGINE'	=> 'InnoDB',
                'VERSION'	=> '10',
                'ROW_FORMAT'	=> 'Dynamic',
                'TABLE_ROWS'	=> '0',
                'AVG_ROW_LENGTH'	=> '0',
                'DATA_LENGTH'	=> '16384',
                'MAX_DATA_LENGTH'	=> '0',
                'INDEX_LENGTH'	=> '0',
                'DATA_FREE'	=> '0',
                'AUTO_INCREMENT'	=> '',
                'CREATE_TIME'	=> '10/16/2018 18:33',
                'UPDATE_TIME'	=> '',
                'CHECK_TIME'	=> '',
                'TABLE_COLLATION'	=> 'utf8mb4_0900_ai_ci',
                'CHECKSUM'	=> '',
                'CREATE_OPTIONS'	=> '',
                'TABLE_COMMENT'	=> 'table 1',
                'Db'	=> 'test',
                'Name'	=> 'table1',
                'Engine'	=> 'InnoDB',
                'Type'	=> 'InnoDB',
                'Version'	=> '10',
                'Row_format'	=> 'Dynamic',
                'Rows'	=> '0',
                'Avg_row_length'	=> '0',
                'Data_length'	=> '16384',
                'Max_data_length'	=> '0',
                'Index_length'	=> '0',
                'Data_free'	=> '0',
                'Auto_increment'	=> '',
                'Create_time'	=> '10/16/2018 18:33',
                'Update_time'	=> '',
                'Check_time'	=> '',
                'Collation'	=> 'utf8mb4_0900_ai_ci',
                'Checksum'	=> '',
                'Create_options'	=> '',
                'Comment'	=> 'table 1',
            ],
        ]);
    }

    /**
     * Tests for DBI::checkDbExtension() method.
     *
     * @return void
     * @test
     */
    public function testCheckDbExtension()
    {
        $this->assertTrue( $this->_dbi->checkDbExtension());
    }

    /**
     * Tests for DBI::getCachedTableContent() method.
     *
     * @return void
     * @test
     */
    public function testCheckTableCache()
    {
        $GLOBALS['cfg']['Server']['DisableIS'] = false;
        $tables = $this->_dbi->getTablesFull($database = 'test');
        $cached_tables = $this->_dbi->getCachedTableContent(['test']);
        $this->assertEquals($tables, $cached_tables);
        $this->_dbi->clearTableCache();
        $cached_tables = $this->_dbi->getCachedTableContent([]);
        $this->assertEquals([], $cached_tables);
    }

    /**
     * Tests for DBI::tryQuery() method with debug.
     *
     * @return void
     * @test
     */
    public function testTryQuery()
    {
        $GLOBALS['cfg']['DBG']['sql'] = true;
        $GLOBALS['cfg']['DBG']['sqllog'] = true;
        $one = $this->_dbi->tryQuery(
            "SELECT 1",
            $link = DatabaseInterface::CONNECT_USER,
            $options = DatabaseInterface::QUERY_STORE,
            $cache_affected_rows = true);
        $this->assertEquals($_SESSION['debug']['queries'][0]['query'], 'SELECT 1');
    }

    /**
     * Tests for DBI::tryQuery() method with debug.
     *
     * @return void
     * @test
     * @throws \ReflectionException
     */
    public function test_getTableCondition()
    {
        $method = $this->getPrivateMethod('_getTableCondition');
        $result = $method->invokeArgs($this->_dbi, [
            ['car','manuf'],
            true,
            'view'
        ]);
        $this->assertEquals($result,'AND t.`TABLE_NAME`  IN (\'car\', \'manuf\') AND t.`TABLE_TYPE` != \'BASE TABLE\'');
    }
}
