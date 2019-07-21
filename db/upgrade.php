<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file keeps track of upgrades to
 * the reactforum module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package   mod_reactforum
 * @copyright 2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_reactforum_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2014051201) {

        // Incorrect values that need to be replaced.
        $replacements = array(
            11 => 20,
            12 => 50,
            13 => 100
        );

        // Run the replacements.
        foreach ($replacements as $old => $new) {
            $DB->set_field('reactforum', 'maxattachments', $new, array('maxattachments' => $old));
        }

        // ReactForum savepoint reached.
        upgrade_mod_savepoint(true, 2014051201, 'reactforum');
    }

    if ($oldversion < 2014081500) {

        // Define index course (not unique) to be added to reactforum_discussions.
        $table = new xmldb_table('reactforum_discussions');
        $index = new xmldb_index('course', XMLDB_INDEX_NOTUNIQUE, array('course'));

        // Conditionally launch add index course.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // ReactForum savepoint reached.
        upgrade_mod_savepoint(true, 2014081500, 'reactforum');
    }

    if ($oldversion < 2014081900) {

        // Define table reactforum_discussion_subs to be created.
        $table = new xmldb_table('reactforum_discussion_subs');

        // Adding fields to table reactforum_discussion_subs.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reactforum', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('discussion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('preference', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');

        // Adding keys to table reactforum_discussion_subs.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('reactforum', XMLDB_KEY_FOREIGN, array('reactforum'), 'reactforum', array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        $table->add_key('discussion', XMLDB_KEY_FOREIGN, array('discussion'), 'reactforum_discussions', array('id'));
        $table->add_key('user_discussions', XMLDB_KEY_UNIQUE, array('userid', 'discussion'));

        // Conditionally launch create table for reactforum_discussion_subs.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // ReactForum savepoint reached.
        upgrade_mod_savepoint(true, 2014081900, 'reactforum');
    }

    if ($oldversion < 2014103000) {
        // Find records with multiple userid/postid combinations and find the lowest ID.
        // Later we will remove all those which don't match this ID.
        $sql = "
            SELECT MIN(id) as lowid, userid, postid
            FROM {reactforum_read}
            GROUP BY userid, postid
            HAVING COUNT(id) > 1";

        if ($duplicatedrows = $DB->get_recordset_sql($sql)) {
            foreach ($duplicatedrows as $row) {
                $DB->delete_records_select('reactforum_read', 'userid = ? AND postid = ? AND id <> ?', array(
                    $row->userid,
                    $row->postid,
                    $row->lowid,
                ));
            }
        }
        $duplicatedrows->close();

        // ReactForum savepoint reached.
        upgrade_mod_savepoint(true, 2014103000, 'reactforum');
    }

    if ($oldversion < 2014110300) {

        // Changing precision of field preference on table reactforum_discussion_subs to (10).
        $table = new xmldb_table('reactforum_discussion_subs');
        $field = new xmldb_field('preference', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'discussion');

        // Launch change of precision for field preference.
        $dbman->change_field_precision($table, $field);

        // ReactForum savepoint reached.
        upgrade_mod_savepoint(true, 2014110300, 'reactforum');
    }

    // Moodle v2.8.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v2.9.0 release upgrade line.
    // Put any upgrade step following this.
    if ($oldversion < 2015102900) {
        // Groupid = 0 is never valid.
        $DB->set_field('reactforum_discussions', 'groupid', -1, array('groupid' => 0));

        // ReactForum savepoint reached.
        upgrade_mod_savepoint(true, 2015102900, 'reactforum');
    }

    // Moodle v3.0.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2015120800) {

        // Add support for pinned discussions.
        $table = new xmldb_table('reactforum_discussions');
        $field = new xmldb_field('pinned', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timeend');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // ReactForum savepoint reached.
        upgrade_mod_savepoint(true, 2015120800, 'reactforum');
    }
    // Moodle v3.1.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2016091200) {

        // Define field lockdiscussionafter to be added to reactforum.
        $table = new xmldb_table('reactforum');
        $field = new xmldb_field('lockdiscussionafter', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'displaywordcount');

        // Conditionally launch add field lockdiscussionafter.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // ReactForum savepoint reached.
        upgrade_mod_savepoint(true, 2016091200, 'reactforum');
    }

    // Automatically generated Moodle v3.2.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2017071216) {
        $table = new xmldb_table('reactforum_discussions');
        $field = new xmldb_field('reactiontype', XMLDB_TYPE_CHAR, '50', null, true, false, 'text', 'pinned');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2017071216, 'reactforum');
    }

    if ($oldversion < 2017072700) {
        $table = new xmldb_table('reactforum');
        $field = new xmldb_field('reactiontype', XMLDB_TYPE_CHAR, '50', null, true, false, 'text', 'displaywordcount');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        //

        $table = new xmldb_table('reactforum_reactions');

        $field = new xmldb_field('reactforum_id', XMLDB_TYPE_INTEGER, '10', null, null, false, '0', 'id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $key = new xmldb_key('reactforum_id', XMLDB_KEY_FOREIGN, array('reactforum_id'), 'reactforum', 'id');
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }

        $index = new xmldb_index('discussion_idx', XMLDB_INDEX_NOTUNIQUE, array('discussion_id'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('reactforum_idx', XMLDB_INDEX_NOTUNIQUE, array('reactforum_id'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        //

        $table = new xmldb_table('reactforum_user_reactions');

        $index = new xmldb_index('post_idx', XMLDB_INDEX_NOTUNIQUE, array('post_id'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('user_idx', XMLDB_INDEX_NOTUNIQUE, array('user_id'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('reaction_idxs', XMLDB_INDEX_NOTUNIQUE, array('reaction_id'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        //

        upgrade_mod_savepoint(true, 2017072700, 'reactforum');
    }

    // Upgrade for ReactForum Moodle 3.2

    // Automatically generated Moodle v3.3.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2017092200) {

        // Remove duplicate entries from reactforum_subscriptions.
        // Find records with multiple userid/reactforum combinations and find the highest ID.
        // Later we will remove all those entries.
        $sql = "
            SELECT MIN(id) as minid, userid, reactforum
            FROM {reactforum_subscriptions}
            GROUP BY userid, reactforum
            HAVING COUNT(id) > 1";

        if ($duplicatedrows = $DB->get_recordset_sql($sql)) {
            foreach ($duplicatedrows as $row) {
                $DB->delete_records_select('reactforum_subscriptions',
                    'userid = :userid AND reactforum = :reactforum AND id <> :minid', (array)$row);
            }
        }
        $duplicatedrows->close();

        // Define key useridreactforum (primary) to be added to reactforum_subscriptions.
        $table = new xmldb_table('reactforum_subscriptions');
        $key = new xmldb_key('useridreactforum', XMLDB_KEY_UNIQUE, array('userid', 'reactforum'));

        // Launch add key useridreactforum.
        $dbman->add_key($table, $key);

        // ReactForum savepoint reached.
        upgrade_mod_savepoint(true, 2017092200, 'reactforum');
    }

    if ($oldversion < 2017100900) {
        $table = new xmldb_table('reactforum');
        $field = new xmldb_field('lockdiscussionafter', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, false, '0', 'reactiontype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2017100900, 'reactforum');
    }

    if ($oldversion < 2017110100) {
        $table = new xmldb_table('reactforum');
        $field = new xmldb_field('reactionallreplies', XMLDB_TYPE_INTEGER, '1', null, true, null, '0', 'reactiontype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $reactforums = $DB->get_records('reactforum');
        foreach ($reactforums as $reactforum) {
            $reactforum->reactionallreplies = 1;
            $DB->update_record('reactforum', $reactforum);
        }

        $table = new xmldb_table('reactforum_discussions');
        $field = new xmldb_field('reactionallreplies', XMLDB_TYPE_INTEGER, '1', null, true, null, '0', 'reactiontype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $discussions = $DB->get_records('reactforum_discussions');
        foreach ($discussions as $discussion) {
            if ($discussion->reactiontype) {
                $discussion->reactionallreplies = 1;
                $DB->update_record('reactforum_discussions', $discussion);
            }
        }

        upgrade_mod_savepoint(true, 2017110100, 'reactforum');
    }

    // Automatically generated Moodle v3.4.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2018032900) {

        // Define field deleted to be added to reactforum_posts.
        $table = new xmldb_table('reactforum_posts');
        $field = new xmldb_field('deleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'mailnow');

        // Conditionally launch add field deleted.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // ReactForum savepoint reached.
        upgrade_mod_savepoint(true, 2018032900, 'reactforum');
    }

    // Automatically generated Moodle v3.5.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2018041600) {
        $table = new xmldb_table('reactforum');
        $field = new xmldb_field('delayedcounter', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'reactionallreplies');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2018041600, 'reactforum');
    }

    if ($oldversion < 2018041601) {
        $table = new xmldb_table('reactforum');
        $field = new xmldb_field('delayed_counter', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'reactionallreplies');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'delayedcounter');
        }

        upgrade_mod_savepoint(true, 2018041601, 'reactforum');
    }

    if ($oldversion < 2019012201) {
        $DB->execute(
            'UPDATE {reactforum_reactions} r
            SET reactforum_id = (SELECT reactforum FROM {reactforum_discussions} d WHERE d.id = r.discussion_id)
            WHERE r.reactforum_id = 0'
        );

        upgrade_mod_savepoint(true, 2019012201, 'reactforum');
    }

    if ($oldversion < 2019072135) {

        $table = new xmldb_table('reactforum_posts');
        $field = new xmldb_field('deleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'mailnow');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2019072135, 'reactforum');
    }

    return true;
}
