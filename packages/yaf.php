<?php
/**
 * YetAnotherForum.NET exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license Proprietary
 * @package VanillaPorter
 */

$Supported['yaf'] = array('name' => 'YAF.NET', 'prefix' => 'yaf_'); // (Yet Another Forum)
$Supported['yaf']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'PrivateMessages' => 1,
    'Signatures' => 1,
    'Ranks' => 1,
    'Passwords' => 1,
);

class Yaf extends ExportController {
    public static $PasswordFormats = array(0 => 'md5', 1 => 'sha1', 2 => 'sha256', 3 => 'sha384', 4 => 'sha512');

    /**
     *
     * @param ExportModel $Ex
     */
    public function forumExport($Ex) {

        $CharacterSet = $Ex->getCharacterSet('Topic');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        $Ex->beginExport('', 'YAF.NET (Yet Another Forum)');
        $Ex->SourcePrefix = 'yaf_';

        // User.
        $User_Map = array(
            'UserID' => 'UserID',
            'Name' => 'Name',
            'Email' => 'Email',
            'Joined' => 'DateInserted',
            'LastVisit' => array('Column' => 'DateLastVisit', 'Type' => 'datetime'),
            'IP' => 'InsertIPAddress',
            'Avatar' => 'Photo',
            'RankID' => array('Column' => 'RankID', 'Type' => 'int'),
            'Points' => array('Column' => 'Points', 'Type' => 'int'),
            'LastActivity' => 'DateLastActive',
            'Password2' => array('Column' => 'Password', 'Filter' => array($this, 'ConvertPassword')),
            'HashMethod' => 'HashMethod'
        );
        $Ex->exportTable('User', "
         select
            u.*,
            m.Password as Password2,
            m.PasswordSalt,
            m.PasswordFormat,
            m.LastActivity,
            'yaf' as HashMethod
         from :_User u
         left join :_prov_Membership m
            on u.ProviderUserKey = m.UserID;", $User_Map);

        // Role.
        $Role_Map = array(
            'GroupID' => 'RoleID',
            'Name' => 'Name'
        );
        $Ex->exportTable('Role', "
         select *
         from :_Group;", $Role_Map);

        // UserRole.
        $UserRole_Map = array(
            'UserID' => 'UserID',
            'GroupID' => 'RoleID'
        );
        $Ex->exportTable('UserRole', 'select * from :_UserGroup', $UserRole_Map);

        // Rank.
        $Rank_Map = array(
            'RankID' => 'RankID',
            'Level' => 'Level',
            'Name' => 'Name',
            'Label' => 'Label'
        );
        $Ex->exportTable('Rank', "
         select
            r.*,
            RankID as Level,
            Name as Label
         from :_Rank r;", $Rank_Map);

        // Signatures.
        $Ex->exportTable('UserMeta', "
         select
            UserID,
            'Plugin.Signatures.Sig' as `Name`,
            Signature as `Value`
         from :_User
         where Signature <> ''

         union all

         select
            UserID,
            'Plugin.Signatures.Format' as `Name`,
            'BBCode' as `Value`
         from :_User
         where Signature <> '';");

        // Category.
        $Category_Map = array(
            'ForumID' => 'CategoryID',
            'ParentID' => 'ParentCategoryID',
            'Name' => 'Name',
            'Description' => 'Description',
            'SortOrder' => 'Sort'
        );

        $Ex->exportTable('Category', "
         select
            f.ForumID,
            case when f.ParentID = 0 then f.CategoryID * 1000 else f.ParentID end as ParentID,
            f.Name,
            f.Description,
            f.SortOrder
         from :_Forum f

         union all

         select
            c.CategoryID * 1000,
            null,
            c.Name,
            null,
            c.SortOrder
         from :_Category c;", $Category_Map);

        // Discussion.
        $Discussion_Map = array(
            'TopicID' => 'DiscussionID',
            'ForumID' => 'CategoryID',
            'UserID' => 'InsertUserID',
            'Posted' => 'DateInserted',
            'Topic' => 'Name',
            'Views' => 'CountViews',
            'Announce' => 'Announce'
        );
        $Ex->exportTable('Discussion', "
         select
            case when t.Priority > 0 then 1 else 0 end as Announce,
            t.Flags & 1 as Closed,
            t.*
         from :_Topic t
         where t.IsDeleted = 0;", $Discussion_Map);

        // Comment.
        $Comment_Map = array(
            'MessageID' => 'CommentID',
            'TopicID' => 'DiscussionID',
            'ReplyTo' => array('Column' => 'ReplyToCommentID', 'Type' => 'int'),
            'UserID' => 'InsertUserID',
            'Posted' => 'DateInserted',
            'Message' => 'Body',
            'Format' => 'Format',
            'IP' => 'InsertIPAddress',
            'Edited' => array('Column' => 'DateUpdated', 'Filter' => array($this, 'CleanDate')),
            'EditedBy' => 'UpdateUserID'
        );
        $Ex->exportTable('Comment', "
         select
            case when m.Flags & 1 = 1 then 'Html' else 'BBCode' end as Format,
            m.*
         from :_Message m
         where IsDeleted = 0;", $Comment_Map);

        // Conversation.
        $this->_exportConversationTemps();

        $Conversation_Map = array(
            'PMessageID' => 'ConversationID',
            'FromUserID' => 'InsertUserID',
            'Created' => 'DateInserted',
            'Title' => array('Column' => 'Subject', 'Type' => 'varchar(512)')
        );
        $Ex->exportTable('Conversation', "
         select
            pm.*,
            g.Title
         from z_pmgroup g
         join :_PMessage pm
            on g.Group_ID = pm.PMessageID;", $Conversation_Map);

        // UserConversation.
        $UserConversation_Map = array(
            'PM_ID' => 'ConversationID',
            'User_ID' => 'UserID',
            'Deleted' => 'Deleted'
        );
        $Ex->exportTable('UserConversation', "
         select pto.*
         from z_pmto pto
         join z_pmgroup g
            on pto.PM_ID = g.Group_ID;", $UserConversation_Map);

        // ConversationMessage.
        $ConversationMessage_Map = array(
            'PMessageID' => 'MessageID',
            'Group_ID' => 'ConversationID',
            'FromUserID' => 'InsertUserID',
            'Created' => 'DateInserted',
            'Body' => 'Body',
            'Format' => 'Format'
        );
        $Ex->exportTable('ConversationMessage', "
         select
            pm.*,
            case when pm.Flags & 1 = 1 then 'Html' else 'BBCode' end as Format,
            t.Group_ID
         from :_PMessage pm
         join z_pmtext t
            on t.PM_ID = pm.PMessageID;", $ConversationMessage_Map);

        $Ex->endExport();
    }

    public function cleanDate($Value) {
        if (!$Value) {
            return null;
        }
        if (substr($Value, 0, 4) == '0000') {
            return null;
        }

        return $Value;
    }

    public function convertPassword($Hash, $ColumnName, &$Row) {
        $Salt = $Row['PasswordSalt'];
        $Hash = $Row['Password2'];
        $Method = $Row['PasswordFormat'];
        if (isset(self::$PasswordFormats[$Method])) {
            $Method = self::$PasswordFormats[$Method];
        } else {
            $Method = 'sha1';
        }
        $Result = $Method . '$' . $Salt . '$' . $Hash . '$';

        return $Result;
    }

    protected function _exportConversationTemps() {
        $Sql = "
         drop table if exists z_pmto;

         create table z_pmto (
            PM_ID int unsigned,
            User_ID int,
            Deleted tinyint,
            primary key(PM_ID, User_ID)
            );

         insert ignore z_pmto (
            PM_ID,
            User_ID,
            Deleted
         )
         select
            PMessageID,
            FromUserID,
            0
         from :_PMessage;

         replace z_pmto (
            PM_ID,
            User_ID,
            Deleted
         )
         select
            PMessageID,
            UserID,
            IsDeleted
         from :_UserPMessage;

         drop table if exists z_pmto2;
         create table z_pmto2 (
            PM_ID int unsigned,
             UserIDs varchar(250),
             primary key (PM_ID)
         );

         replace z_pmto2 (
            PM_ID,
            UserIDs
         )
         select
            PM_ID,
            group_concat(User_ID order by User_ID)
         from z_pmto
         group by PM_ID;

         drop table if exists z_pmtext;
         create table z_pmtext (
            PM_ID int unsigned,
            Title varchar(250),
             Title2 varchar(250),
             UserIDs varchar(250),
             Group_ID int unsigned
         );

         insert z_pmtext (
            PM_ID,
            Title,
            Title2
         )
         select
            PMessageID,
            Subject,
            case when Subject like 'Re:%' then trim(substring(Subject, 4)) else Subject end as Title2
         from :_PMessage;

         create index z_idx_pmtext on z_pmtext (PM_ID);

         update z_pmtext pm
         join z_pmto2 t
            on pm.PM_ID = t.PM_ID
         set pm.UserIDs = t.UserIDs;

         drop table if exists z_pmgroup;

         create table z_pmgroup (
                 Group_ID int unsigned,
                 Title varchar(250),
                 UserIDs varchar(250)
               );

         insert z_pmgroup (
                 Group_ID,
                 Title,
                 UserIDs
               )
               select
                 min(pm.PM_ID),
                 pm.Title2,
                 t2.UserIDs
               from z_pmtext pm
               join z_pmto2 t2
                 on pm.PM_ID = t2.PM_ID
               group by pm.Title2, t2.UserIDs;

         create index z_idx_pmgroup on z_pmgroup (Title, UserIDs);
         create index z_idx_pmgroup2 on z_pmgroup (Group_ID);

         update z_pmtext pm
               join z_pmgroup g
                 on pm.Title2 = g.Title and pm.UserIDs = g.UserIDs
               set pm.Group_ID = g.Group_ID;";

        $this->Ex->queryN($Sql);
    }
}

// Closing PHP tag required. (make.php)
?>
