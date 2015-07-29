<?php

// vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork;

class BouncePdoAdapter
{
    protected $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function messageIdExists($message_id)
    {
        $sql = 'SELECT COUNT(*) FROM postfix_stats WHERE last_sent_hash=?';
        $req = $this->db->prepare($sql);
        $req->execute(array(hexdec(substr(md5($message_id), 16))));
        $sql = $req->fetchColumn();
        $req->closeCursor();

        return $sql;
    }

    public function getAuthSentTime($sender, $recipients)
    {
        $sender = array($sender => '');

        foreach ($recipients as $recipients) {
            foreach ($recipients as $recipients) {
                if (null !== $recipients) {
                    $sender += $recipients;
                }
            }
        }

        $sender = array_keys($sender);
        $sender = array_map(array($this->db, 'quote'), $sender);
        $sql = implode(',', $sender);
        $sql = "SELECT MIN(IF(email={$sender[0]},last_from,last_sent))
                FROM postfix_stats
                WHERE email IN ({$sql})
                HAVING count(*)=".count($sender);
        $req = $this->db->query($sql);
        $sql = $req->fetchColumn();
        $req->closeCursor();

        return false === $sql ? null : $sql;
    }

    public function recordParseResults($results)
    {
        if (empty($results)) {
            return;
        }

        $sql = array();

        foreach ($results as $r) {
            $s = array_map(array($this, 'quote'), $r);
            $s = implode(',', $s);
            $sql[] = $s;
        }

        $r = array_keys($r);

        $sql = 'INSERT INTO postfix_log_bounces ('
            .implode(',', $r).") VALUES\n("
            .implode("),\n(", $sql).')';

        $this->db->query($sql);
    }

    public function quote($s)
    {
        if (null === $s) {
            return 'NULL';
        }

        return $this->db->quote($s);
    }
}
