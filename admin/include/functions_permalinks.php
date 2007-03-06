<?php
// +-----------------------------------------------------------------------+
// | PhpWebGallery - a PHP based picture gallery                           |
// | Copyright (C) 2003-2007 PhpWebGallery Team - http://phpwebgallery.net |
// +-----------------------------------------------------------------------+
// | file          : $Id: admin/function_permalinks.inc.php$
// | last update   : $Date$
// | last modifier : $Author$
// | revision      : $Revision$
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

/** deletes the permalink associated with a category
 * returns true on success
 * @param int cat_id the target category id
 * @param boolean save if true, the current category-permalink association
 * is saved in the old permalinks table in case external links hit it
 */
function delete_cat_permalink( $cat_id, $save )
{
  global $page, $cache;
  $query = '
SELECT permalink
  FROM '.CATEGORIES_TABLE.'
  WHERE id="'.$cat_id.'"
;';
  $result = pwg_query($query);
  if ( mysql_num_rows($result) )
  {
    list($permalink) = mysql_fetch_array($result);
  }
  if ( !isset($permalink) )
  {// no permalink; nothing to do
    return true;
  }
  if ($save)
  {
    $old_cat_id = get_cat_id_from_old_permalink($permalink, false);
    if ( isset($old_cat_id) and $old_cat_id!=$cat_id )
    {
      $page['errors'][] = 
        sprintf( 
          l10n('Permalink_%s_histo_used_by_%s'),
          $permalink, $old_cat_id
        );
      return false;
    }
  }
  $query = '
UPDATE '.CATEGORIES_TABLE.'
  SET permalink=NULL
  WHERE id='.$cat_id.'
  LIMIT 1';
  pwg_query($query);
  
  unset( $cache['cat_names'] ); //force regeneration
  if ($save)
  {
    if ( isset($old_cat_id) )
    {
      $query = '
UPDATE '.OLD_PERMALINKS_TABLE.'
  SET date_deleted=NOW()
  WHERE cat_id='.$cat_id.' AND permalink="'.$permalink.'"';
    }
    else
    {
      $query = '
INSERT INTO '.OLD_PERMALINKS_TABLE.'
  (permalink, cat_id, date_deleted)
VALUES
  ( "'.$permalink.'",'.$cat_id.',NOW() )';
    }
    pwg_query( $query );
  }
  return true;
}

/** sets a new permalink for a category
 * returns true on success
 * @param int cat_id the target category id
 * @param string permalink the new permalink
 * @param boolean save if true, the current category-permalink association
 * is saved in the old permalinks table in case external links hit it
 */
function set_cat_permalink( $cat_id, $permalink, $save )
{
  global $page, $cache;
  
  $sanitized_permalink = preg_replace( '#[^a-zA-Z0-9_-]#', '' ,$permalink);
  if ( $sanitized_permalink != $permalink 
      or preg_match( '#^(\d)+(-.*)?$#', $permalink) )
  {
    $page['errors'][] = l10n('Permalink_name_rule');
    return false;
  }
  
  // check if the new permalink is actively used
  $existing_cat_id = get_cat_id_from_permalink( $permalink );
  if ( isset($existing_cat_id) )
  {
    if ( $existing_cat_id==$cat_id )
    {// no change required
      return true;
    }
    else
    {
      $page['errors'][] = 
        sprintf( 
          l10n('Permalink %s is already used by category %s'),
          $permalink, $existing_cat_id 
        );
      return false;
    }
  }

  // check if the new permalink was historically used
  $old_cat_id = get_cat_id_from_old_permalink($permalink, false);
  if ( isset($old_cat_id) and $old_cat_id!=$cat_id )
  {
    $page['errors'][] = 
      sprintf( 
        l10n('Permalink_%s_histo_used_by_%s'),
        $permalink, $old_cat_id
      );
    return false;
  }

  if ( !delete_cat_permalink($cat_id, $save ) )
  {
    return false;
  }

  if ( isset($old_cat_id) )
  {// the new permalink must not be active and old at the same time
    assert( $old_cat_id==$cat_id );
    $query = '
DELETE FROM '.OLD_PERMALINKS_TABLE.'
  WHERE cat_id='.$old_cat_id.' AND permalink="'.$permalink.'"';
    pwg_query($query);
  }
  
  $query = '
UPDATE '.CATEGORIES_TABLE.'
  SET permalink="'.$permalink.'"
  WHERE id='.$cat_id.'
  LIMIT 1';
  pwg_query($query);

  unset( $cache['cat_names'] ); //force regeneration
  
  return true;
}

?>
