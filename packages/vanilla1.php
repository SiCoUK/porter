<?php
/**
 * Vanilla 1 exporter tool
 *
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$supported['vanilla1'] = array('name' => 'Vanilla 1', 'prefix' => 'LUM_');
$supported['vanilla1']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Attachments' => 1,
    'PrivateMessages' => 1,
    'Passwords' => 1,
    'Bookmarks' => 1,
);

class Vanilla1 extends ExportController {

    /** @var array Required tables => columns */
    public $sourceTables = array(
        'User' => array('UserID', 'Name', 'Password', 'Email', 'CountComments'),
        'Role' => array('RoleID', 'Name', 'Description'),
        'Category' => array('CategoryID', 'Name', 'Description'),
        'Discussion' => array(
            'DiscussionID',
            'Name',
            'CategoryID',
            'DateCreated',
            'AuthUserID',
            'DateLastActive',
            'Closed',
            'Sticky',
            'CountComments',
            'Sink',
            'LastUserID'
        ),
        'Comment' => array(
            'CommentID',
            'DiscussionID',
            'AuthUserID',
            'DateCreated',
            'EditUserID',
            'DateEdited',
            'Body',
            'Deleted'
        )
    );

    /**
     * Forum-specific export format
     * @todo Project file size / export time and possibly break into multiple files
     * @param ExportModel $ex
     *
     */
    protected function forumExport($ex) {
        $this->ex = $ex;

        $characterSet = $ex->getCharacterSet('Comment');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        // Begin
        $ex->beginExport('', 'Vanilla 1.*');

        // Users
        $user_Map = array(
            'UserID' => 'UserID',
            'Name' => 'Name',
            'Password' => 'Password',
            'Email' => 'Email',
            'Icon' => 'Photo',
            'CountComments' => 'CountComments',
            'Discovery' => 'DiscoveryText'
        );
        $ex->exportTable('User', "SELECT * FROM :_User", $user_Map);  // ":_" will be replaced by database prefix

        // Roles

        // Since the zero role is a valid role in Vanilla 1 then we'll have to reassign it.
        $r = $ex->query('select max(RoleID) as RoleID from :_Role');
        $zeroRoleID = 0;
        if (is_resource($r)) {
            while ($row = $r->nextResultRow()) {
                $zeroRoleID = $row['RoleID'];
            }
        }
        $zeroRoleID++;

        /*
            'RoleID' => 'int',
            'Name' => 'varchar(100)',
            'Description' => 'varchar(200)'
         */
        $role_Map = array(
            'RoleID' => 'RoleID',
            'Name' => 'Name',
            'Description' => 'Description'
        );
        $ex->exportTable('Role',
            "select RoleID, Name, Description from :_Role union all select $zeroRoleID, 'Applicant', 'Created by the Vanilla Porter'",
            $role_Map);

        $permission_Map = array(
            'RoleID' => 'RoleID',
            'PERMISSION_SIGN_IN' => 'Garden.SignIn.Allow',
            'Permissions' => array(
                'Column' => 'Vanilla.Comments.Add',
                'Type' => 'tinyint',
                'Filter' => array($this, 'filterPermissions')
            ),
            'PERMISSION_START_DISCUSSION' => array(
                'Column' => 'Vanilla.Discussions.Add',
                'Type' => 'tinyint',
                'Filter' => array($this, 'forceBool')
            ),
            'PERMISSION_SINK_DISCUSSION' => array(
                'Column' => 'Vanilla.Discussions.Sink',
                'Type' => 'tinyint',
                'Filter' => array($this, 'forceBool')
            ),
            'PERMISSION_STICK_DISCUSSIONS' => array(
                'Column' => 'Vanilla.Discussions.Announce',
                'Type' => 'tinyint',
                'Filter' => array($this, 'forceBool')
            ),
            'PERMISSION_CLOSE_DISCUSSIONS' => array(
                'Column' => 'Vanilla.Discussions.Close',
                'Type' => 'tinyint',
                'Filter' => array($this, 'forceBool')
            ),
            'PERMISSION_EDIT_DISCUSSIONS' => array(
                'Column' => 'Vanilla.Discussions.Edit',
                'Type' => 'tinyint',
                'Filter' => array($this, 'forceBool')
            ),
            'PERMISSION_EDIT_COMMENTS' => array(
                'Column' => 'Vanilla.Comments.Edit',
                'Type' => 'tinyint',
                'Filter' => array($this, 'forceBool')
            ),
            'PERMISSION_APPROVE_APPLICANTS' => array(
                'Column' => 'Garden.Moderation.Manage',
                'Type' => 'tinyint',
                'Filter' => array($this, 'forceBool')
            ),
            'PERMISSION_EDIT_USERS' => array(
                'Column' => 'Garden.Users.Edit',
                'Type' => 'tinyint',
                'Filter' => array($this, 'forceBool')
            ),
            'PERMISSION_CHANGE_APPLICATION_SETTINGS' => array(
                'Column' => 'Garden.Settings.Manage',
                'Type' => 'tinyint',
                'Filter' => array($this, 'forceBool')
            )
        );
        $ex->exportTable('Permission', "select * from :_Role", $permission_Map);

        // UserRoles
        /*
            'UserID' => 'int',
            'RoleID' => 'int'
         */
        $userRole_Map = array(
            'UserID' => 'UserID',
            'RoleID' => 'RoleID'
        );
        $ex->exportTable('UserRole',
            "select UserID, case RoleID when 0 then $zeroRoleID else RoleID end as RoleID from :_User", $userRole_Map);

        // Categories
        /*
            'CategoryID' => 'int',
            'Name' => 'varchar(30)',
            'Description' => 'varchar(250)',
            'ParentCategoryID' => 'int',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int'
         */
        $category_Map = array(
            'CategoryID' => 'CategoryID',
            'Name' => 'Name',
            'Description' => 'Description'
        );
        $ex->exportTable('Category', "select CategoryID, Name, Description from :_Category", $category_Map);

        // Discussions
        /*
            'DiscussionID' => 'int',
            'Name' => 'varchar(100)',
            'CategoryID' => 'int',
            'Body' => 'text',
            'Format' => 'varchar(20)',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int',
            'Score' => 'float',
            'Announce' => 'tinyint',
            'Closed' => 'tinyint'
         */
        $discussion_Map = array(
            'DiscussionID' => 'DiscussionID',
            'Name' => 'Name',
            'CategoryID' => 'CategoryID',
            'DateCreated' => 'DateInserted',
            'DateCreated2' => 'DateUpdated',
            'AuthUserID' => 'InsertUserID',
            'DateLastActive' => 'DateLastComment',
            'AuthUserID2' => 'UpdateUserID',
            'Closed' => 'Closed',
            'Sticky' => 'Announce',
            'CountComments' => 'CountComments',
            'Sink' => 'Sink',
            'LastUserID' => 'LastCommentUserID'
        );
        $ex->exportTable('Discussion',
            "SELECT d.*,
            d.LastUserID as LastCommentUserID,
            d.DateCreated as DateCreated2, d.AuthUserID as AuthUserID2,
            c.Body,
            c.FormatType as Format
         FROM :_Discussion d
         LEFT JOIN :_Comment c
            ON d.FirstCommentID = c.CommentID
         WHERE coalesce(d.WhisperUserID, 0) = 0 and d.Active = 1", $discussion_Map);

        // Comments
        /*
            'CommentID' => 'int',
            'DiscussionID' => 'int',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int',
            'Format' => 'varchar(20)',
            'Body' => 'text',
            'Score' => 'float'
         */
        $comment_Map = array(
            'CommentID' => 'CommentID',
            'DiscussionID' => 'DiscussionID',
            'AuthUserID' => 'InsertUserID',
            'DateCreated' => 'DateInserted',
            'EditUserID' => 'UpdateUserID',
            'DateEdited' => 'DateUpdated',
            'Body' => 'Body',
            'FormatType' => 'Format'
        );
        $ex->exportTable('Comment', "
         SELECT
            c.*
         FROM :_Comment c
         JOIN :_Discussion d
            ON c.DiscussionID = d.DiscussionID
         WHERE d.FirstCommentID <> c.CommentID
            AND c.Deleted = '0'
            AND coalesce(d.WhisperUserID, 0) = 0
            AND coalesce(c.WhisperUserID, 0) = 0", $comment_Map);

        $ex->exportTable('UserDiscussion', "
         SELECT
            w.UserID,
            w.DiscussionID,
            w.CountComments,
            w.LastViewed as DateLastViewed,
            case when b.UserID is not null then 1 else 0 end AS Bookmarked
         FROM :_UserDiscussionWatch w
         LEFT JOIN :_UserBookmark b
            ON w.DiscussionID = b.DiscussionID AND w.UserID = b.UserID");

        // Conversations

        // Create a mapping tables for conversations.
        // These mapping tables are used to group comments that a) are in the same discussion and b) are from and to the same users.

        $ex->query("drop table if exists z_pmto");

        $ex->query("create table z_pmto (
  CommentID int,
  UserID int,
  primary key(CommentID, UserID)
 )");

        $ex->query("insert ignore z_pmto (
  CommentID,
  UserID
)
select distinct
  CommentID,
  AuthUserID
from :_Comment
where coalesce(WhisperUserID, 0) <> 0");

        $ex->query("insert ignore z_pmto (
  CommentID,
  UserID
)
select distinct
  CommentID,
  WhisperUserID
from :_Comment
where coalesce(WhisperUserID, 0) <> 0");

        $ex->query("insert ignore z_pmto (
  CommentID,
  UserID
)
select distinct
  c.CommentID,
  d.AuthUserID
from :_Discussion d
join :_Comment c
  on c.DiscussionID = d.DiscussionID
where coalesce(d.WhisperUserID, 0) <> 0");

        $ex->query("insert ignore z_pmto (
  CommentID,
  UserID
)
select distinct
  c.CommentID,
  d.WhisperUserID
from :_Discussion d
join :_Comment c
  on c.DiscussionID = d.DiscussionID
where coalesce(d.WhisperUserID, 0) <> 0");

        $ex->query("insert ignore z_pmto (
  CommentID,
  UserID
)
select distinct
  c.CommentID,
  c.AuthUserID
from :_Discussion d
join :_Comment c
  on c.DiscussionID = d.DiscussionID
where coalesce(d.WhisperUserID, 0) <> 0");

        $ex->query("drop table if exists z_pmto2");

        $ex->query("create table z_pmto2 (
  CommentID int,
  UserIDs varchar(250),
  primary key (CommentID)
)");

        $ex->query("insert z_pmto2 (
  CommentID,
  UserIDs
)
select
  CommentID,
  group_concat(UserID order by UserID)
from z_pmto
group by CommentID");


        $ex->query("drop table if exists z_pm");

        $ex->query("create table z_pm (
  CommentID int,
  DiscussionID int,
  UserIDs varchar(250),
  GroupID int
)");

        $ex->query("insert ignore z_pm (
  CommentID,
  DiscussionID
)
select
  CommentID,
  DiscussionID
from :_Comment
where coalesce(WhisperUserID, 0) <> 0");

        $ex->query("insert ignore z_pm (
  CommentID,
  DiscussionID
)
select
  c.CommentID,
  c.DiscussionID
from :_Discussion d
join :_Comment c
  on c.DiscussionID = d.DiscussionID
where coalesce(d.WhisperUserID, 0) <> 0");

        $ex->query("update z_pm pm
join z_pmto2 t
  on t.CommentID = pm.CommentID
set pm.UserIDs = t.UserIDs");

        $ex->query("drop table if exists z_pmgroup");

        $ex->query("create table z_pmgroup (
  GroupID int,
  DiscussionID int,
  UserIDs varchar(250)
)");

        $ex->query("insert z_pmgroup (
  GroupID,
  DiscussionID,
  UserIDs
)
select
  min(pm.CommentID),
  pm.DiscussionID,
  t2.UserIDs
from z_pm pm
join z_pmto2 t2
  on pm.CommentID = t2.CommentID
group by pm.DiscussionID, t2.UserIDs");

        $ex->query("create index z_idx_pmgroup on z_pmgroup (DiscussionID, UserIDs)");

        $ex->query("create index z_idx_pmgroup2 on z_pmgroup (GroupID)");

        $ex->query("update z_pm pm
join z_pmgroup g
  on pm.DiscussionID = g.DiscussionID and pm.UserIDs = g.UserIDs
set pm.GroupID = g.GroupID");

        $conversation_Map = array(
            'AuthUserID' => 'InsertUserID',
            'DateCreated' => 'DateInserted',
            'DiscussionID' => array('Column' => 'DiscussionID', 'Type' => 'int'),
            'CommentID' => 'ConversationID',
            'Name' => array('Column' => 'Subject', 'Type' => 'varchar(255)')
        );
        $ex->exportTable('Conversation',
            "select c.*, d.Name
from :_Comment c
join :_Discussion d
  on d.DiscussionID = c.DiscussionID
join z_pmgroup g
  on g.GroupID = c.CommentID;", $conversation_Map);

        // ConversationMessage.
        $conversationMessage_Map = array(
            'CommentID' => 'MessageID',
            'GroupID' => 'ConversationID',
            'Body' => 'Body',
            'FormatType' => 'Format',
            'AuthUserID' => 'InsertUserID',
            'DateCreated' => 'DateInserted'
        );
        $ex->exportTable('ConversationMessage',
            "select c.*, pm.GroupID
from z_pm pm
join :_Comment c
  on pm.CommentID = c.CommentID", $conversationMessage_Map);

        // UserConversation
        /*
           'UserID' => 'int',
           'ConversationID' => 'int',
           'LastMessageID' => 'int'
        */
        $userConversation_Map = array(
            'UserID' => 'UserID',
            'GroupID' => 'ConversationID'
        );
        $ex->exportTable('UserConversation',
            "select distinct
  pm.GroupID,
  t.UserID
from z_pmto t
join z_pm pm
  on pm.CommentID = t.CommentID", $userConversation_Map);

        $ex->query("drop table z_pmto");
        $ex->query("drop table z_pmto2");
        $ex->query("drop table z_pm");
        $ex->query("drop table z_pmgroup");

        // Media
        if ($ex->exists('Attachment')) {
            $media_Map = array(
                'AttachmentID' => 'MediaID',
                'Name' => 'Name',
                'MimeType' => 'Type',
                'Size' => 'Size',
                'Path' => array('Column' => 'Path', 'Filter' => array($this, 'stripMediaPath')),
                'UserID' => 'InsertUserID',
                'DateCreated' => 'DateInserted',
                'CommentID' => 'ForeignID'
                //'ForeignTable'
            );
            $ex->exportTable('Media',
                "select a.*, 'comment' as ForeignTable from :_Attachment a",
                $media_Map);
        }

        // End
        $ex->endExport();
    }

    public function stripMediaPath($absPath) {
        if (($pos = strpos($absPath, '/uploads/')) !== false) {
            return substr($absPath, $pos + 9);
        }

        return $absPath;
    }

    public function filterPermissions($permissions, $columnName, &$row) {
        $permissions2 = unserialize($permissions);

        foreach ($permissions2 as $name => $value) {
            if (is_null($value)) {
                $permissions2[$name] = false;
            }
        }

        if (is_array($permissions2)) {
            $row = array_merge($row, $permissions2);
            $this->ex->currentRow = $row;

            return isset($permissions2['PERMISSION_ADD_COMMENTS']) ? $permissions2['PERMISSION_ADD_COMMENTS'] : false;
        }

        return false;
    }

    public function forceBool($value) {
        if ($value) {
            return true;
        }

        return false;
    }
}

// Closing PHP tag required. (make.php)
?>
