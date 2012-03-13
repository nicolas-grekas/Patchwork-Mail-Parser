<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

class Patchwork_BouncePdoAdapter
{
    protected $db;

    function __construct(PDO $db)
    {
        $this->db = $db;
    }

    function countMessageId($message_id, $envelope)
    {
        $sql = 'SELECT COUNT(*) FROM postfix_stats WHERE last_sent_hash=?';
        $req = $this->db->prepare($sql);
        $req->execute(array(hexdec(substr(md5($message_id), 16))));
        $sql = $req->fetchColumn();
        $req->closeCursor();

        return $sql;
    }

    function getAuthSentTime($sender, $recipients)
    {
        $sender = array($sender => '');

        foreach ($recipients as $recipients)
            foreach ($recipients as $recipients)
                if (null !== $recipients)
                    $sender += $recipients;

        $sender = array_keys($sender);
        $sender = array_map(array($this->db, 'quote'), $sender);
        $sql = implode(',', $sender);
        $sql = "SELECT MIN(IF(email={$sender[0]},last_from,last_sent))
                FROM postfix_stats
                WHERE email IN ({$sql})
                HAVING count(*)=" . count($sender);
        $req = $this->db->query($sql);
        $sql = $req->fetchColumn();
        $req->closeCursor();

        return false === $sql ? null : $sql;
    }

    function recordParseResults($results)
    {
        if (empty($results)) return;

        $sql = array();

        foreach ($results as $r)
        {
            $s = array_map(array($this, 'quote'), $r);
            $s = implode(',', $s);
            $sql[] = $s;
        }

        $r = array_keys($r);

        $sql = 'INSERT INTO postfix_log_bounces ('
            . implode(',', $r) . ") VALUES\n("
            . implode("),\n(", $sql) . ')';

        $this->db->query($sql);
    }

    function quote($s)
    {
        if (null === $s) return 'NULL';
        return $this->db->quote($s);
    }
}
