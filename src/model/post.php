<?php

include_once $_SERVER['DOCUMENT_ROOT'] . "/src/model/meta_object.php";

/*
 *  Class:              Post
 *  Description:        Class used to create, delete and manage Posts
 */
class Post extends MetaObject
{
 
    private $name;
    private $url;
    private $content;
    private $status;
    private $category_id;

    public function __construct(
        $name,
        $url,
        $content,
        $category_id,
        $status,
        $lang,
        $id,
        $default_lang_ref_id,
        $meta_title,
        $meta_description
    ) {
        parent::__construct($lang, $id, $default_lang_ref_id, $meta_title, $meta_description);

        $this->name = $name;
        $this->url = $url;
        $this->content = $content;
        $this->status = $status;
        $this->category_id = $category_id;
    }

    public static function byId( $conn, $id ) {
        $stmt = $conn->prepare("SELECT * FROM post p INNER JOIN language l ON l.lang_id = p.post_lang_id WHERE post_id = ?");
        $stmt->bind_param("i", $id );
        $stmt->execute();
        if( $record = $stmt->get_result()->fetch_assoc() ) {
            //$stmt->close();
            return new self(
                $record['post_name'],
                $record['post_url'],
                $record['post_content'],
                $record['post_category_id'],
                $record['post_status'],
                Language::byData(
                    $record['lang_id'],
                    $record['lang_name'],
                    $record['lang_code']
                ),
                $record['post_id'],
                $record['post_lang_ref'],
                $record['post_meta_title'],
                $record['post_meta_description']
            );    
        }
        return NULL;
        $stmt->close();
        
    }

    public static function delete($conn, $id)
    {
        $sql_text = <<<EOD
    SELECT
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
        REFERENCED_TABLE_SCHEMA = ?
        AND REFERENCED_TABLE_NAME = 'post';
EOD;
        $stmt = $conn->prepare($sql_text);
        $stmt->bind_param("s", DBUtils::readCredentials()['database']);
        $stmt->execute();
        $stmt_result = $stmt->get_result();
        while ($record = $stmt_result->fetch_assoc()) {
            $table_name = $record['TABLE_NAME'];
            $column_name = $record['COLUMN_NAME'];
            
            echo "Tabella $table_name colonna: $column_name | ";
            if ($record['TABLE_NAME'] != "post") {
                $sql_text = "SELECT COUNT(*) FROM $table_name WHERE $column_name = ?";
                $stmt_check = $conn->prepare($sql_text);
                $stmt_check->bind_param("i", $id);
                $stmt_check->execute();
            } else {
                $sql_text = "SELECT COUNT(*) FROM $table_name WHERE $column_name = ? AND post_id != ?";
                $stmt_check = $conn->prepare($sql_text);
                $stmt_check->bind_param("ii", $id, $id);
                $stmt_check->execute();
            }

            $rows_count = $stmt_check->get_result()->fetch_assoc()['COUNT(*)'];

            if ($rows_count > 0) {
                $stmt_check->close();
                $stmt->close();
                return false;
            }

            $stmt_check->close();
        }
        $stmt->close();
        $conn->query("SET foreign_key_checks = 0");
        $sql_text = "DELETE FROM post WHERE post_id = ?";
        $stmt = $conn->prepare($sql_text);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $conn->query("SET foreign_key_checks = 1");
        return true;
    }

    public function update( $conn ) {
        
        $lang_id = $this->lang->getLangId();

        $stmt = $conn->prepare( "UPDATE post SET post_name = ?, post_url = ?, post_content = ?, post_status = ?, " . 
                                "post_category_id = ?, post_lang_id = ?, post_meta_title = ?, post_meta_description = ? WHERE post_id = ?");
        $stmt->bind_param("sssiiissi", $this->name, $this->url, $this->content, $this->status, $this->category_id,
                                    $lang_id, $this->meta_title, $this->meta_description, $this->id );
        $stmt->execute();
        
        $stmt->close();
    }

    public function fetchByLang($conn, $lang)
    {
        $sql_text = "SELECT * FROM post p INNER JOIN language lang ON lang.lang_id = p.post_lang_id WHERE lang.lang_id = " . $lang->getLangId() . " AND p.post_lang_ref = " . $this->default_lang_ref_id . " LIMIT 1";
        $result = $conn->query($sql_text);

        if ($record = $result->fetch_assoc()) {
            return new self(
                $record['post_name'],
                $record['post_url'],
                $record['post_content'],
                $record['post_category_id'],
                $record['post_status'],
                Language::byData(
                    $record['lang_id'],
                    $record['lang_name'],
                    $record['lang_code']
                ),
                $record['post_id'],
                $record['post_lang_ref'],
                $record['post_meta_title'],
                $record['post_meta_description']
            );
        }
    }

    public function fetchByDefaultLang($conn)
    {
        return $this->fetchByLang($conn, Language::getDefaultLanguage($conn));
    }

    public static function fetchAll($conn)
    {
        $sql_text = "SELECT * FROM post p INNER JOIN language lang ON lang.lang_id = p.post_lang_id";
        $result = $conn->query($sql_text);

        $categories = array();

        while ($record = $result->fetch_assoc()) {
            $categories[] =  new self(
                $record['post_name'],
                $record['post_url'],
                $record['post_content'],
                $record['post_category_id'],
                $record['post_status'],
                Language::byData(
                    $record['lang_id'],
                    $record['lang_name'],
                    $record['lang_code']
                ),
                $record['post_id'],
                $record['post_lang_ref'],
                $record['post_meta_title'],
                $record['post_meta_description']
            );
        }

        return $categories;
    }

    public static function fetchAllByLang($conn, $lang)
    {
        $sql_text = "SELECT * FROM post p INNER JOIN language lang ON lang.lang_id = p.post_lang_id WHERE lang.lang_id = " . $lang->getLangId();
        $result = $conn->query($sql_text);

        $categories = array();

        while ($record = $result->fetch_assoc()) {
            $categories[] =  new self(
                $record['post_name'],
                $record['post_url'],
                $record['post_content'],
                $record['post_category_id'],
                $record['post_status'],
                Language::byData(
                    $record['lang_id'],
                    $record['lang_name'],
                    $record['lang_code']
                ),
                $record['post_id'],
                $record['post_lang_ref'],
                $record['post_meta_title'],
                $record['post_meta_description']
            );
        }

        return $categories;
    }


    public static function addNew(
        $conn,
        $name,
        $url,
        $content,
        $cat_id,
        $status,
        $lang,
        $default_lang_ref_id,
        $meta_title,
        $meta_description
    ) {
        $langId = $lang->getLangId();

        if (is_null($default_lang_ref_id)) {
            $sql_text = <<<EOD
INSERT INTO post (post_name,post_content,post_category_id,post_status,post_url,post_lang_id,post_lang_ref,post_meta_title,post_meta_description)
VALUES ( ?, ?, ?, ?, ?, ?,NULL, ?, ?);
EOD;
            $stmt = $conn->prepare($sql_text);
            $stmt->bind_param("ssiisiss", $name, $content, $cat_id, $status, $url, $langId, $meta_title, $meta_description);
            $stmt->execute();
            $stmt->close();

            $id = $conn->query("SELECT LAST_INSERT_ID();")->fetch_assoc()['LAST_INSERT_ID()'];

            $conn->query("UPDATE post set post_lang_ref=$id WHERE post_id=$id");
        } else {
            $sql_text = <<<EOD
INSERT INTO post (post_name,post_content,post_category_id,post_status,post_url,post_lang_id,post_lang_ref,post_meta_title,post_meta_description)
VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?);
EOD;
            $stmt = $conn->prepare($sql_text);
            $stmt->bind_param("ssiisiiss", $name, $content, $cat_id, $status, $url, $langId, $default_lang_ref_id, $meta_title, $meta_description);
            $stmt->execute();
            $stmt->close();
        }
    }

    public static function fetchAllByDefaultLang($conn)
    {
        return Category::fetchAllByLang($conn, Language::getDefaultLanguage($conn));
    }
 
    public static function fetchTranslationsByRef( $ref, $conn ) {
        $sql_text = "SELECT * FROM post p INNER JOIN language lang ON lang.lang_id = p.post_lang_id WHERE post_lang_ref = ?";
        $stmt = $conn->prepare($sql_text);
        $stmt->bind_param("i",$ref );
        $stmt->execute();
        

        $result = $stmt->get_result();
        $stmt->close();
        $posts = array();

        while ($record = $result->fetch_assoc()) {
            $posts[] =  new self(
                $record['post_name'],
                $record['post_url'],
                $record['post_content'],
                $record['post_category_id'],
                $record['post_status'],
                Language::byData(
                    $record['lang_id'],
                    $record['lang_name'],
                    $record['lang_code']
                ),
                $record['post_id'],
                $record['post_lang_ref'],
                $record['post_meta_title'],
                $record['post_meta_description']
            );
        }

        return $posts;
    }

    public function fetchTranslations( $conn ) {
        return Post::fetchTranslationsByRef( $this->getDefaultLangRefId() );
    }


    /*      GETTER - SETTER methods     */

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setContent($content)
    {
        $this->content = $content;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function getCategoryId() {
        return $this->category_id;
    }

    public function setCategoryId( $category_id ) {
        $this->category_id = $category_id;
    }
}
