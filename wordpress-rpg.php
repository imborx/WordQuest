<?php
/*
Plugin Name: Wordpress RPG
Plugin URI: 
Description: What feature is WordPress severely lacking? Goblins. Dragons. This plugin is here to fix that.
Author: Ian Hunter
Version: 0.0
Author URI: www.ianhunter.ie
*/

/*
    Wordpress RPG
    Copyright (C) 2014  Ian Hunter ianfhunter@gmail.com

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License along
    with this program; if not, write to the Free Software Foundation, Inc.,
    51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

#Returns a user's active quest from file
function get_quest(){
    $file = WP_PLUGIN_DIR."/Wordpress-RPG/experience" . get_current_user_id() . ".rpg"; 
    if (!file_exists($file)){
        $quest = add_quest();
    }else{
        $json = file_get_contents ( $file );
        $jsonD = json_decode($json);
        $quest = $jsonD->{"quest"};
    }
    return $quest;
}


#The info display in the top right hand corner.
function admin_info_header() {

    $file = WP_PLUGIN_DIR."/Wordpress-RPG/experience".get_current_user_id().".rpg"; 
    if (!file_exists($file)){
        $quest = add_quest();
        $experience = 0;

    }else{
        $json = file_get_contents ( $file );
        $jsonD = json_decode($json);
        $experience = $jsonD->{"total_experience"};
        $quest = $jsonD->{"quest"};
    }

    $stats = calc_level($experience);
    # Classes Here - Templar, etc
    echo '<div style="float:right">⚔ Level ' . $stats[0] . " " . player_class() . ' - ' . $stats[1] . '/' . $stats[2] .' Exp - Quest: "'. $quest.'"</div>';
}

#Calculates what level a user is.
# @param - Total experience:
# @modifier: 1.6
# @Returns: Level, Remaining Exp, Next Level EXP
function calc_level($experience){
    $level = 500;
    $modifier = 1.6;
    $count = 0;
    while($experience - $level > 0){
        $experience = $experience - $level;
        $level = $level * $modifier;
        $count = $count + 1;
    }
    return array($count,round($experience),round($level));
}

#Extracts the longest word from a string
function reduce($v, $p) {
    return strlen($v) > strlen($p) ? $v : $p;
}

function player_class(){
    $file = WP_PLUGIN_DIR."/Wordpress-RPG/stats" . get_current_user_id() . ".rpg"; 
    if (file_exists($file)){
        $file = WP_PLUGIN_DIR."/Wordpress-RPG/stats" . get_current_user_id() . ".rpg"; 
        $json = file_get_contents ( $file );
        $jsonD = json_decode($json,true);

        #Get the highest value from our stats
        $value = max($jsonD);
        $keys = array_keys($jsonD,$value);
        #Combined classes?

        return ucfirst($keys[0]);
    
    }else{
        return "Wanderer";
    }
}

function calculate_stats($input){
    $file = WP_PLUGIN_DIR."/Wordpress-RPG/stats" . get_current_user_id() . ".rpg"; 
    if (!file_exists($file)){
        $stats = array( "dwarf"       => 0,
                         "giant"       => 0,
                         "bard"        => 0,
                         "illusionist" => 0,
                         "oracle"      => 0,
                         "wizard"      => 0,
                         "assassin"    => 0,
                         "priest"      => 0,
                         );
    }else{
        #Read existing stats into the array    
        $json = file_get_contents ( $file );
        $stats = json_decode($json,true);
    }


    #Based on post length:
    if(strlen($input) < 200){
        $stats["dwarf"]++;        
    }else if(strlen($input) > 1000){
        $stats["giant"]++;
    }
    
    #Based on content:
    if (strpos($input , "la" ) !== False){
        $stats["bard"]++; 
    }
    if (strpos ( $input , "img" ) !== False){
        $stats["illusionist"]++;
    }
    if (strpos ( $input , "youtube" ) !== False){
        $stats["oracle"]++;
    }
    if (strpos ( $input , "svg" ) !== False){
        $stats["wizard"]++;
    }
    
    $longest_word = array_reduce(str_word_count($input, 1), 'reduce');
    if (strlen($longest_word) < 6){
        $stats["assassin"]++;
    }else if (strlen($longest_word) > 12){
        $stats["priest"]++;
    }
    $json = json_encode($stats);
    file_put_contents ( $file ,$json );
}

#Adds experience upon posting, currently based on character count
function add_experience() {
    #Making sure that experience is only added on newly published items    
    if( ( $_POST['post_status'] == 'publish' ) && ( $_POST['original_post_status'] != 'publish' ) ) {
        $file = WP_PLUGIN_DIR."/Wordpress-RPG/experience" . get_current_user_id() . ".rpg"; 
        if (!file_exists($file)){
            $experience = 0;
            $quest = add_quest();
        }else{
            $json = file_get_contents ( $file );
            $jsonD = json_decode($json);
            $experience = $jsonD->{"total_experience"};
            $quest = $jsonD->{"quest"};
            

            #Check if our quest category matches a category we published this post under. +100xp Bonus
            $filed_under = ($_POST["post_category"]);
            $cat_list = get_categories();
            $filed_names = array();

            for($i = 0; $i != count($filed_under) - 1;$i = $i + 1){
                array_push($filed_names, $cat_list[$filed_under[$i]]->{"cat_name"});
            }

            #If quest fufilled, Give a bonus 100xp and allocate new quest. TODO: Notification?
            if (in_array($quest, $filed_names)){
                $experience = $experience + 100;
                $quest = add_quest();
            }
        }

        $exp = strlen($_POST['content']) + $experience;
        calculate_stats($_POST['content']);
        $json = json_encode(array( 
                                   "total_experience" => $exp, 
                                   "quest" => $quest
                                  ));
        file_put_contents ( $file ,$json );
    }
}

#Activates a quest
function add_quest(){
    $cat_list = get_categories();
    $quest = $cat_list[array_rand($cat_list)];
    return $quest->{"cat_name"};
}

function quest_metabox(){
    add_meta_box("rpg-metabox", "Wordpress RPG", draw_metabox, 'post', 'side', 'high');
}

function idle_messages(){
    $file = WP_PLUGIN_DIR."/Wordpress-RPG/idle.rpg"; 
    $contents = explode("\n",file_get_contents($file));
    return $contents;
}

#
function get_heroavatar(){
    $available_avatars = 7;    #TODO: generate this from the amount of files in the levels folder
    #Get level
    $file = WP_PLUGIN_DIR."/Wordpress-RPG/experience".get_current_user_id().".rpg"; 
    if (!file_exists($file)){
        $quest = add_quest();
        $experience = 0;

    }else{
        $json = file_get_contents ( $file );
        $jsonD = json_decode($json);
        $experience = $jsonD->{"total_experience"};
        $quest = $jsonD->{"quest"};
        $quest = $quest;
    }
    $stats = calc_level($experience);
    $level = $stats[0];

    $n = 0;
    $count = 1;
    while($level > $n && $n != $available_avatars - 1){
        $n = $n + ($n + 1);     #n = current Triangular Number        
        $count = $count + 1;    #count = 'X'th triangular number
    }
    return $count;     #As files are indexed from 0

}

function draw_metabox(){
    #Would be really nice to rotate through some phrases here.
    #And also have avatars based on your character
    #Credit: http://leon-murayami.deviantart.com/art/Illusion-of-Gaia-Will-XP-402827050

    echo " <img src='".plugins_url()."/Wordpress-RPG/levels/".get_heroavatar() .".gif' /> <div id='idle_msg'>" . "Killing some slimes... " . "</div>" . "Current Quest: Write about '" . get_quest() . "'";
    $params = array(
      'messages' => idle_messages(),
    );
    wp_register_script('rotation_script',plugins_url().'/Wordpress-RPG/idle_messages.js');
    wp_localize_script('rotation_script', 'object_name', $params );
    wp_enqueue_script( 'rotation_script' );

}

add_action('admin_bar_menu', 'admin_info_header');
#add_action('admin_menu', 'rpg_menu' );
add_action('publish_post', 'add_experience' ); 
add_action('add_meta_boxes', 'quest_metabox' ); 

?>
