<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


/**
 * Bulk insert SMDP locations safely and efficiently
 *
 * @param array  $locations  Example: [['label'=>'Dhaka','parent_id'=>0, 'sys_id'=>0], ...]
 * @param string $provider  e.g. 'pathao'
 * @param int    $chunk_size  Max rows per SQL query (default 200)
 * @return array {inserted:int, skipped:int, total:int}
 */
function smdp_add_locations_bulk_safe(array $locations, string $provider, int $chunk_size = 200) {
    if (empty($locations) || empty($provider)) {
        return ['inserted' => 0, 'skipped' => 0, 'total' => 0];
    }
    error_log('saving.. '.$provider);
    global $wpdb;
    $table = $wpdb->prefix . 'smdp_locations';
    $provider = sanitize_text_field($provider);

    $total = count($locations);
    $inserted = 0;

    // Split into chunks for memory safety
    $chunks = array_chunk($locations, $chunk_size);
    foreach ($chunks as $chunk) {
        $values = [];

        foreach ($chunk as $loc) {
            $label = sanitize_text_field($loc['label'] ?? '');
            if (empty($label)) continue;
            $parent_id = intval($loc['parent_id'] ?? 0);
            $sys_id = intval($loc['sys_id'] ?? 0);
            // Safely prepare each row
            $values[] = $wpdb->prepare("(%s, %d,%d, %s)", $label, $parent_id, $sys_id, $provider);
        }

        if (empty($values)) continue;

        // Build and run INSERT IGNORE query
        $sql = "
            INSERT IGNORE INTO {$table} (label, parent_id, sys_id, provider_name)
            VALUES " . implode(', ', $values);

        $rows = $wpdb->query($sql);
        if ($rows !== false) {
            $inserted += intval($rows);
        }
    }

    // Invalidate cached list for this provider
    delete_transient('smdp_parent_locsn_' . sanitize_key($provider));

    $skipped = $total - $inserted;
    if ($skipped < 0) $skipped = 0;

    return [
        'inserted' => $inserted,
        'skipped'  => $skipped,
        'total'    => $total,
    ];
}



// Add a new city
// $city_id = smdp_add_location('Dhaka',0,'pathaw');

// Add a sub-location under Dhaka
// $area_id = smdp_add_location('Banani', $city_id);
/**
 * Add location (creates new or returns existing)
 */
function smdp_add_location($label, $parent_id = 0, $sys_id = 0, $provider = null) {
    if ($provider === null) return;

    global $wpdb;
    $table = $wpdb->prefix . 'smdp_locations';

    // Sanitize inputs
    $label = sanitize_text_field($label);
    $provider = sanitize_text_field($provider) ?: '-';

    // Check if exists
    $existing = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM $table WHERE label = %s AND provider_name = %s LIMIT 1",
            $label,
            $provider
        )
    );

    if ($existing) {
        return $existing; // already exists
    }

    // Insert new row
    $wpdb->insert(
        $table,
        [
            'label' => $label,
            'parent_id' => intval($parent_id),
            'sys_id' => intval($parent_id),
            'provider_name' => $provider,
        ],
        ['%s', '%d', '%d', '%s']
    );

    // 🧹 Clear cache for this provider (to refresh next time)
    delete_transient('smdp_parent_locs_' . sanitize_key($provider));

    return $wpdb->insert_id;
}


/**
 * Update location
 * Example: smdp_update_location($id, 'Banani DOHS', 0, 'pathao');
 */
function smdp_update_location($id, $label = null, $parent_id = null, $provider = null) {
    if ($provider === null) return;

    global $wpdb;
    $table = $wpdb->prefix . 'smdp_locations';

    $data = [];
    $where = ['id' => intval($id)];
    $formats = [];

    if ($label !== null) { $data['label'] = sanitize_text_field($label); $formats[] = '%s'; }
    if ($parent_id !== null) { $data['parent_id'] = intval($parent_id); $formats[] = '%d'; }
    if ($provider !== null) { $data['provider_name'] = sanitize_text_field($provider); $formats[] = '%s'; }

    if (!empty($data)) {
        $wpdb->update($table, $data, $where, $formats, ['%d']);

        // 🧹 Clear cache after update
        delete_transient('smdp_parent_locs_' . sanitize_key($provider));

        return true;
    }

    return false;
}


/**
 * Delete location (optionally recursive)
 * Example: smdp_delete_location($id, true);
 */
function smdp_delete_location($id, $delete_children = false, $provider = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'smdp_locations';

    $id = intval($id);

    if ($delete_children) {
        $children = smdp_get_child_locations($id, $provider);
        foreach ($children as $child) {
            smdp_delete_location($child->id, true, $child->provider_name ?? $provider);
        }
    }

    $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);

    // 🧹 Clear cache if provider name known
    if ($provider) {
        delete_transient('smdp_parent_locs_' . sanitize_key($provider));
    }

    return $deleted;
}


/**
 * Get parent locations (those without a parent)
 *
 * @return array List of parent locations as associative arrays.
 */
function smdp_get_parent_locations($provider = null,$nocache= false) {
    if (empty($provider)) {
        return [];
    }
    
    if($nocache){
        delete_transient('smdp_parent_locs_' . sanitize_key($provider));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'smdp_locations';
    $cache_key  = 'smdp_parent_locs_' . sanitize_key($provider);

    delete_transient($cache_key);

    // Try cache first
    $results = get_transient($cache_key);
   
    if ($results !== false && is_array($results)) {
        if(count($results) == 0){
            error_log('cache cleared');
            delete_transient($cache_key);                        
        }else{
            error_log('From cached Data');
            return $results;
        }
        
    }

    // Check table existence only once per load (static cache)
    static $table_exists = [];
    if (!isset($table_exists[$table_name])) {
        $table_exists[$table_name] = ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name);
    }

    if (!$table_exists[$table_name]) {
        return [];
    }

    // Fetch from DB
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, label, parent_id, sys_id
             FROM {$table_name}
             WHERE provider_name = %s AND parent_id = 0
             ORDER BY label ASC",
            $provider
        ),
        ARRAY_A
    );

    // Store in transient for 7 days
    set_transient($cache_key, $results, 7 * DAY_IN_SECONDS);

    return $results ?: [];
}



/**
 * Get child locations for a given parent_id with caching
 *
 * @param int $parent_id
 * @return array
 */
function smdp_get_child_locations($parent_id = 0,$provider='') {
    global $wpdb;

    $parent_id = intval($parent_id);
    $table     = $wpdb->prefix . 'smdp_locations';
    $cache_key = 'smdp_child_locs_' . $parent_id;
    
    // Try cache first
    $results = get_transient($cache_key);
    
    if ($results !== false && is_array($results)) {
        if(count($results) == 0){
            error_log('cache cleared');
            delete_transient($cache_key);  
                      
        }else{
            error_log('From cached Data');
            return $results;
        }
        
    }

   
    // Check table existence only once per request
    static $table_exists = [];
    if (!isset($table_exists[$table])) {
        $table_exists[$table] = ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table);
    }

    if (!$table_exists[$table]) {
        return [];
    }

    // Fetch from DB
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE parent_id = %d ORDER BY label ASC",
            $parent_id
        ),
        ARRAY_A
    );

    // Cache results for 7 days
    set_transient($cache_key, $results, 7 * DAY_IN_SECONDS);
    
    return $results ?: [];
}



function smdp_get_location($id) {
    global $wpdb;
    $id = intval($id);
    if ($id <= 0) return null;

    $table     = $wpdb->prefix . 'smdp_locations';
    $cache_key = 'smdp_loc_' . $id;

    // Try cache
    $result = get_transient($cache_key);
    if ($result !== false && is_array($result)) {
        return $result;
    }

    // Check table existence once per request
    static $table_exists = [];
    if (!isset($table_exists[$table])) {
        $table_exists[$table] = ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table);
    }

    if (!$table_exists[$table]) {
        return null;
    }

    $result = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id),
        ARRAY_A
    );

    if ($result) {
        set_transient($cache_key, $result, 7 * DAY_IN_SECONDS);
    }

    return $result ?: null;
}


// Find by label
// $found = smdp_find_location_by_label('Dhaka');
function smdp_find_location_by_label($label, $provider = null, $parent_id = 0) {
    if (empty($label) || empty($provider)) {
        return null;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'smdp_locations';
    $label = sanitize_text_field($label);
    $provider = sanitize_text_field($provider);
    $parent_id = intval($parent_id);

    $cache_key = 'smdp_loc_label_' . md5($label . '|' . $provider . '|' . $parent_id);

    // Try cache
    $result = get_transient($cache_key);
    if ($result !== false && is_array($result)) {
        return $result;
    }

    // Check table existence once per request
    static $table_exists = [];
    if (!isset($table_exists[$table])) {
        $table_exists[$table] = ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table);
    }

    if (!$table_exists[$table]) {
        return null;
    }

    $result = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE label = %s AND provider_name = %s AND parent_id = %d LIMIT 1",
            $label,
            $provider,
            $parent_id
        ),
        ARRAY_A
    );

    if ($result) {
        set_transient($cache_key, $result, 7 * DAY_IN_SECONDS);
    }

    return $result ?: null;
}




function smdp_remove_duplicate_locations() {
    global $wpdb;
    $table = $wpdb->prefix . 'smdp_locations';

    // MySQL query: delete duplicates, keeping the row with the smallest id
    $sql = "
        DELETE l1 FROM {$table} l1
        INNER JOIN {$table} l2 
            ON l1.provider_name = l2.provider_name 
            AND l1.sys_id = l2.sys_id 
            AND l1.id > l2.id
    ";

    $deleted = $wpdb->query($sql);

    if ($deleted !== false) {
        error_log("SMDP: Removed {$deleted} duplicate location rows.");
        return $deleted;
    } else {
        error_log("SMDP: Failed to remove duplicates!");
        return false;
    }
}



