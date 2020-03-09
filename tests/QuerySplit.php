<?php

namespace Stack\Tests;

use PHPUnit\Framework\TestCase;
use Stack\QuerySplit;

class QuerySplitUnitTest extends TestCase
{

    const MASTER_LABEL = "/*route:master*/";
    const SLAVE_LABEL = "/*route:slave*/";

    public function writeQueriesDataProvider()
    {
        return [
            ["SELECT test FROM test;", ";"],
            ["select test from test;", ";"],
            ["SHOW tables;", ";"],
            ["SELECT 1;", ";"],

            ["INSERT INTO test VALUES ();", self::MASTER_LABEL],
            ["SELECT test FROM test FOR UPDATE;", self::MASTER_LABEL],
            ["other;", self::MASTER_LABEL],
        ];
    }

    /**
     *
     * @dataProvider writeQueriesDataProvider
     *
     */
    public function testIsWriteQuery($query, $suffix)
    {
        $qs = new QuerySplit();

        $this->assertStringEndsWith($suffix, $qs->processQuery($query));
    }

    public function testQuerySplitSetup()
    {
        $_SERVER['REQUEST_URI'] = "/wp/wp-admin/something";

        $qs = new QuerySplit();

        $this->assertStringEndsWith(self::MASTER_LABEL, $qs->processQuery("SELECT 1;"));
    }
}
