<?php
/**
 * Joomla Kunena exporter tool
 *
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$supported['kunena'] = array('name' => 'Joomla Kunena', 'prefix' => 'jos_');
$supported['kunena']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Avatars' => 1,
    'Attachments' => 1,
    'Bookmarks' => 1,
    'Passwords' => 1,
);

class Kunena extends ExportController {
    /**
     * @param ExportModel $ex
     */
    public function forumExport($ex) {

        $characterSet = $ex->getCharacterSet('mbox');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        $ex->destPrefix = 'jos';

        $ex->beginExport('', 'Joomla Kunena', array('HashMethod' => 'joomla'));

        // User.
        $user_Map = array(
            'id' => 'UserID',
            'name' => 'Name',
            'email' => 'Email',
            'registerDate' => 'DateInserted',
            'lastvisitDate' => 'DateLastActive',
            'password' => 'Password',
            'showemail' => 'ShowEmail',
            'birthdate' => 'DateOfBirth',
            'banned' => 'Banned',
//         'DELETED'=>'Deleted',
            'admin' => array('Column' => 'Admin', 'Type' => 'tinyint(1)'),
            'Photo' => 'Photo'
        );
        $ex->exportTable('User', "
         SELECT
            u.*,
            case when ku.avatar <> '' then concat('kunena/avatars/', ku.avatar) else null end as `Photo`,
            case u.usertype when 'superadministrator' then 1 else 0 end as admin,
            coalesce(ku.banned, 0) as banned,
            ku.birthdate,
            !ku.hideemail as showemail
         FROM :_users u
         left join :_kunena_users ku
            on ku.userid = u.id", $user_Map);

        // Role.
        $role_Map = array(
            'rank_id' => 'RoleID',
            'rank_title' => 'Name',
        );
        $ex->exportTable('Role', "select * from :_kunena_ranks", $role_Map);

        // UserRole.
        $userRole_Map = array(
            'id' => 'UserID',
            'rank' => 'RoleID'
        );
        $ex->exportTable('UserRole', "
         select *
         from :_users u", $userRole_Map);

        // Permission.
//      $ex->ExportTable('Permission',
//      "select 2 as RoleID, 'View' as _Permissions
//      union
//      select 3 as RoleID, 'View' as _Permissions
//      union
//      select 16 as RoleID, 'All' as _Permissions", array('_Permissions' => array('Column' => '_Permissions', 'Type' => 'varchar(20)')));

        // Category.
        $category_Map = array(
            'id' => 'CategoryID',
            'parent' => 'ParentCategoryID',
            'name' => 'Name',
            'ordering' => 'Sort',
            'description' => 'Description',

        );
        $ex->exportTable('Category', "
         select * from :_kunena_categories", $category_Map);

        // Discussion.
        $discussion_Map = array(
            'id' => 'DiscussionID',
            'catid' => 'CategoryID',
            'userid' => 'InsertUserID',
            'subject' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'time' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'ip' => 'InsertIPAddress',
            'locked' => 'Closed',
            'hits' => 'CountViews',
            'modified_by' => 'UpdateUserID',
            'modified_time' => array('Column' => 'DateUpdated', 'Filter' => 'timestampToDate'),
            'message' => 'Body',
            'Format' => 'Format'
        );
        $ex->exportTable('Discussion', "
         select
            t.*,
            txt.message,
            'BBCode' as Format
         from :_kunena_messages t
         left join :_kunena_messages_text txt
            on t.id = txt.mesid
         where t.thread = t.id", $discussion_Map);

        // Comment.
        $comment_Map = array(
            'id' => 'CommentID',
            'thread' => 'DiscussionID',
            'userid' => 'InsertUserID',
            'time' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'ip' => 'InsertIPAddress',
            'modified_by' => 'UpdateUserID',
            'modified_time' => array('Column' => 'DateUpdated', 'Filter' => 'timestampToDate'),
            'message' => 'Body',
            'Format' => 'Format'
        );
        $ex->exportTable('Comment', "
         select
            t.*,
            txt.message,
            'BBCode' as Format
         from :_kunena_messages t
         left join :_kunena_messages_text txt
            on t.id = txt.mesid
         where t.thread <> t.id", $comment_Map);

        // UserDiscussion.
        $userDiscussion_Map = array(
            'thread' => 'DiscussionID',
            'userid' => 'UserID'
        );
        $ex->exportTable('UserDiscussion', "
         select t.*, 1 as Bookmarked
         from :_kunena_subscriptions t", $userDiscussion_Map);

        // Media.
        $media_Map = array(
            'id' => 'MediaID',
            'mesid' => 'ForeignID',
            'userid' => 'InsertUserID',
            'size' => 'Size',
            'path2' => array('Column' => 'Path', 'Filter' => 'urlDecode'),
            'thumb_path' => array('Column' => 'ThumbPath', 'Filter' => array($this, 'filterThumbnailData')),
            'thumb_width' => array('Column' => 'ThumbWidth', 'Filter' => array($this, 'filterThumbnailData')),
            'filetype' => 'Type',
            'filename' => array('Column' => 'Name', 'Filter' => 'urlDecode'),
            'time' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
        );
        $ex->exportTable('Media', "
         select
            a.*,
            concat(a.folder, '/', a.filename) as path2,
            case when m.id = m.thread then 'discussion' else 'comment' end as ForeignTable,
            m.time,
            concat(a.folder, '/', a.filename) as thumb_path,
            128 as thumb_width
         from :_kunena_attachments a
         join :_kunena_messages m
            on m.id = a.mesid", $media_Map);

        $ex->endExport();
    }

    /**
     * Filter used by $Media_Map to replace value for ThumbPath and ThumbWidth when the file is not an image.
     *
     * @access public
     * @see ExportModel::_exportTable
     *
     * @param string $ralue Current value
     * @param string $field Current field
     * @param array $row Contents of the current record.
     * @return string|null Return the supplied value if the record's file is an image. Return null otherwise
     */
    public function filterThumbnailData($value, $field, $row) {
        if (strpos(strtolower($row['filetype']), 'image/') === 0) {
            return $value;
        } else {
            return null;
        }
    }
}

// Closing PHP tag required. (make.php)
?>
