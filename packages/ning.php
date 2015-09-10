<?php
/**
 * Ning package for Porter.
 *
 * @copyright Vanilla Forums Inc. 2015
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$Supported['ning'] = array('name' => 'Ning', 'prefix' => '');

$Supported['ning']['CommandLine'] = array();

$Supported['ning']['features'] = array(
    'Categories' => 1,
    'Comments' => 1,
    'Discussions' => 1,
    'Passwords' => 1,
    'Users' => 1
);

/**
 * Ning export controller.
 *
 * @package VanillaPorter
 */
class Ning extends ExportController {
    /** @var array Required tables => columns. */
    protected $sourceTables = array(
        'deletionlog' => array('type', 'primaryid'),
    );

    protected function forumExport($ex) {
        // Determine the character set
        $characterSet = $ex->getCharacterSet('replies');
        if ($characterSet) {
            $ex->CharacterSet = $characterSet;
        } else {
            trigger_error('Unable to determine character set.', E_ERROR);
        }

        $ex->beginExport('', 'Ning');

        // Users.
        $userMap = array();
        $ex->exportTable('User', "select
                id as UserID,
                ning_id as Name,
                created_at as DateInserted,
                updated_at as DateUpdated,
                password_digest as Password,
                'crypt' as HashMethod,
                email as Email,
                topics_count as CountDiscussions,
                replies_count as CountComment,
                if (state = 'blocked', 1, 0) as Banned
            from :_members", $userMap);


        // Roles.
        /**
         * Until we find a reliable way to determine roles and permissions coming from Ning, we use this hack to
         * establish Vanilla's default roles and permissions.
         */
        $roleMap = array();
        $ex->exportTable('Role', "select
                '2' as RoleID,
                'Guest' as Name,
                'Guests can only view content. Anyone browsing the site who is not signed in is considered to be a \"Guest\".' as Description,
                'guest' as Type,
                '1' as Sort,
                '0' as Deleteable,
                '0' as CanSession

            union

            select
                '3' as RoleID,
                'Unconfirmed' as Name,
                'Users must confirm their emails before becoming full members. They get assigned to this role.' as Description,
                'unconfirmed' as Type,
                '2' as Sort,
                '0' as Deleteable,
                '1' as CanSession

            union

            select
                '4' as RoleID,
                'Applicant' as Name,
                'Users who have applied for membership, but have not yet been accepted. They have the same permissions as guests.' as Description,
                'applicant' as Type,
                '3' as Sort,
                '0' as Deleteable,
                '1' as CanSession

            union

            select
                '8' as RoleID,
                'Member' as Name,
                'Members can participate in discussions.' as Description,
                'member' as Type,
                '4' as Sort,
                '1' as Deleteable,
                '1' as CanSession

            union

            select
                '32' as RoleID,
                'Moderator' as Name,
                'Moderators have permission to edit most content.' as Description,
                'moderator' as Type,
                '5' as Sort,
                '1' as Deleteable,
                '1' as CanSession

            union

            select
                '16' as RoleID,
                'Administrator' as Name,
                'Administrators have permission to do anything.' as Description,
                'administrator' as Type,
                '6' as Sort,
                '1' as Deleteable,
                '1' as CanSession", $roleMap);


        // UserRoles.
        /**
         * Until we find a reliable way to determine roles and permissions coming from Ning, we use this hack to
         * assign everyone to the Member role. A single admin will be created at import.
         */
        $userRoleMap = array();
        $ex->exportTable('UserRole', "select id as UserID, '8' as RoleID from :_members", $userRoleMap);


        // Groups.
        $groupMap = array();
        $ex->exportTable('Group', "select
                id as GroupID,
                name as Name,
                description as Description,
                created_at as DateInserted
            from :_groups", $groupMap);


        // UserGroups.
        $userGroupMap = array();
        $ex->exportTable('UserGroup', "select
                group_id as GroupID,
                member_id as UserID,
                'Member' as Role
            from :_groups_members", $userGroupMap);

        // Categories.
        $ex->query("drop table if exists zCategories");
        $ex->query("create table if not exists zCategories (
            CategoryID int(11) unsigned not null auto_increment,
            Name varchar(255) not null default '',
            AllowGroups int(1) unsigned not null default 0,
            primary key (CategoryID),
            unique key Name (Name)
        )");
        $ex->query("insert into zCategories (Name)

            select distinct category
            from topics
            where group_id = '0'");
        $ex->query("insert into zCategories (Name, AllowGroups) values ('Social Groups', '1')");
        $socialGroupsCategory = $ex->get("select CategoryID from zCategories where AllowGroups = '1' limit 1");
        if (empty($socialGroupsCategory)) {
            trigger_error('Unable to locate social groups category', E_ERROR);
        } else {
            $socialGroupsCategoryID = $socialGroupsCategory[0]['CategoryID'];
        }
        $categoryMap = array();
        $ex->exportTable('Category', "select CategoryID, Name, AllowGroups
            from zCategories", $categoryMap);


        // Discussions.
        $discussionMap = array(
            'CategoryID' => array(
                'Column' => 'CategoryID',
                'Filter' => function ($value, $field, $row, $field) use ($socialGroupsCategoryID) {
                    if ($row['GroupID']) {
                        return $socialGroupsCategoryID;
                    } else {
                        return $value;
                    }
                }
            )
        );
        $ex->exportTable('Discussion', "select
                t.id as DiscussionID,
                member_id as InsertUserID,
                t.title as Name,
                t.description_html as Body,
                'Html' as Format,
                c.CategoryID,
                t.replies_count as CountComments,
                t.created_at as DateInserted,
                t.updated_at as DateUpdated,
                t.group_id as GroupID
            from :_topics t left join zCategories c on (t.category = c.Name)", $discussionMap);


        // Comments.
        $commentMap = array();
        $ex->exportTable('Comment', "select
                id as CommentID,
                topic_id as DiscussionID,
                description_html as Body,
                'Html' as Format,
                member_id as InsertUserID,
                created_at as DateInserted,
                updated_at as DateUpdated
            from :_replies", $commentMap);


        // Activities.
        $activityMap = array();
        $ex->exportTable('Activity', "select
                id as ActivityID,
                '18' as ActivityTypeID,
                '-1' as NotifyUserID,
                member_id as ActivityUserID,
                '{ActivityUserID,user}' as HeadlineFormat,
                description_html as Story,
                'Html' as Format,
                member_id as InsertUserID,
                created_at as DateInserted,
                updated_at as DateUpdated
            from :_statuses", $activityMap);


        // Activity Comments.
        $activityCommentMap = array();
        $ex->exportTable('ActivityComment', "select
                id as ActivityCommentID,
                status_id as ActivityID,
                description_html as Body,
                'Html' as Format,
                member_id as InsertUserID,
                created_at as DateInserted
            from :_status_comments", $activityCommentMap);
    }
}
?>
