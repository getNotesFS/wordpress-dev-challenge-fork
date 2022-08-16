<?php
// Loading table class
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Extending class from WP_List_Table
 * 
 */
class Broken_Links_List extends WP_List_Table
{

    private $get_broken_links_list;


    private function get_broken_links_list_from_DB()
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT url,status,origin,origin_post_id from {$wpdb->prefix}wp_broken_links_list",
            ARRAY_A
        );
    }




    // Define table columns
    function get_columns()
    {
        $columns = array(
            'cb'  => '<input type="checkbox" />',

            'url' => 'URL',
            'status' => 'Status',
            'origin' => 'Origin',
        );
        return $columns;
    }

    // Add sorting to columns
    protected function get_sortable_columns()
    {
        $sortable_columns = array(
            'url'  => array('url', true),
            'status' => array('status', false),
            'origin'   => array('origin', false)
        );
        return $sortable_columns;
    }
    // Sorting function
    function usort_reorder($a, $b)
    {
        // If no sort
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'url';

        // If no order, default to asc
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';

        // Determine sort order
        $result = strcmp($a[$orderby], $b[$orderby]);

        // Send final sort direction to usort
        return ($order === 'asc') ? $result : -$result;
    }

    // Bind table with columns, data and all
    function prepare_items()
    {
        $this->get_broken_links_list = $this->get_broken_links_list_from_DB();

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        /* pagination */
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = count($this->get_broken_links_list);

        $this->get_broken_links_list = array_slice($this->get_broken_links_list, (($current_page - 1) * $per_page), $per_page);

        $this->set_pagination_args(array(
            'total_items' => $total_items, // total number of items
            'per_page'    => $per_page // items to show on a page
        ));



        usort($this->get_broken_links_list, array(&$this, 'usort_reorder'));

        $this->items = $this->get_broken_links_list;
    }

    // bind data with column
    function column_default($item, $column_name)
    {
        switch ($column_name) {

            case 'url':
            case 'status':
            case 'origin':
                return $item[$column_name];
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="bl[]" value="%s" />',
            $item['id']
        );
    }
}


/**
 * Function to check link status and save in Database all broken links.
 *
 * @return void
 */
function check_post_content_links_status()
{

    //Get all posts
    $existing_posts = get_posts();


    //Check post by post the links of post_content
    foreach ($existing_posts as $post) {

        $flag = false;
        global $wpdb;
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT count(origin_post_id) as total FROM {$wpdb->prefix}wp_broken_links_list WHERE origin_post_id=%d", $post->ID),
            ARRAY_A
        );

        foreach ($results as $rss) {

            if ($rss['total'] > 0) {
                // echo '<br>Post was checked before: ' . $rss['total'];
                $flag = true;
                // break;
            } else {
                // echo 'New Post';

                $flag = false;
                break;
            }
        }


        //Match all <a></a> tags from post_content
        if (!$flag && preg_match_all('~<a(.*?)href="([^"]+)"(.*?)>~', $post->post_content, $matches)) {

            //Check all url from response -> matches[2] 
            foreach ($matches[2] as $url) {

                //Variables to save url, status and origin
                $send_url = $url;
                $send_status = "";
                $send_origin = $post->ID;

                /**
                 *I tried to use wp_remote_get() for check responses but sometimes didn't work properly, 
                 *sometimes detect good url as  401, 403 so I used both to best results.
                 */

                // Use curl_init() function to initialize a cURL session
                $curl = curl_init($url);

                // Use curl_setopt() to set an option for cURL transfer
                curl_setopt($curl, CURLOPT_NOBODY, true);
                curl_setopt($curl, CURLOPT_HEADER, true);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_TIMEOUT, 10);

                // Use curl_exec() to perform cURL session
                $result = curl_exec($curl);
                if ($result !== false) {

                    // Use curl_getinfo() to get information
                    // regarding a specific transfer
                    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);


                    //Check patterns match with codes of 40x or 50x

                    if (preg_match('/^40|^50/', $statusCode, $matches)) {
                        if ($statusCode == 404) {
                            //echo "(" . $statusCode . ") Not Found=> " . $url . "<br>";

                            //Save status code message
                            $send_status = " (" . $statusCode . ") Not Found";
                            //Check url formats for right messsage
                            check_url_format($url, $send_url, $send_status, $send_origin);
                        } else if ($statusCode == 400) {
                            //echo "(" . $statusCode . ") Bad Request=> " . $url . "<br>";
                            $send_status = " (" . $statusCode . ") Bad Request=> ";
                            check_url_format($url, $send_url, $send_status, $send_origin);
                        } else if ($statusCode == 500) {
                            // echo "(" . $statusCode . ") Internal Server Error=> " . $url . "<br>";
                            $send_status = " (" . $statusCode . ") Internal Server Error=> ";
                            check_url_format($url, $send_url, $send_status, $send_origin);
                        } else {

                            // echo  "Error (" . $statusCode . ") => " . $url . "<br>";
                            $send_status = " Error (" . $statusCode . ") => ";
                            check_url_format($url, $send_url, $send_status, $send_origin);
                        }
                    } //Filter 20x or 30x status code
                    else if (preg_match('/^20|^30/', $statusCode, $matches)) {
                        //echo "(" . $statusCode . ") URL Exist=> " . $url . "<br>";

                        //Double verification of 30x status code message because sometimes is a 404 code property.
                        if (preg_match('/^30/', $statusCode, $matches)) {
                            $response = wp_remote_get($url);
                            $response_code = wp_remote_retrieve_response_code($response);

                            //if is 404 send the correct message
                            if ($response_code == 404) {
                                $send_status = " (" . $response_code . ") Not Found";
                                check_url_format($url, $send_url, $send_status, $send_origin);
                            }
                        }
                    } else {
                        // echo  "Error (" . $statusCode . ") => " . $url . "<br>";

                        //Not identified status code but url is wrong or with another error.
                        $send_status =  "Error (" . $statusCode . ") => ";
                        check_url_format($url, $send_url, $send_status, $send_origin);
                    }
                } else {

                    // echo   "Can't access to URL=> " . $url . "<br>";

                    //Url with bad structure or impossible to access.
                    $send_status = "Can't access to URL=> ";
                    check_url_format($url, $send_url, $send_status, $send_origin);
                }
            }
        }
    }
}

/**
 * Check URL format
 *
 * @param [type] $url
 * @param [type] $_send_url
 * @param [type] $_send_status
 * @param [type] $_send_origin
 * @return void
 */
function check_url_format($url, $_send_url, $_send_status, $_send_origin)
{

    /**
     * Check all patterns that mention in Wordpress Dev Challenge doc.
     */

    //Verify if is https url 
    if (str_contains($url, 'http://') &&  !preg_match('/(:[0-9])\w+/', $url)) {
        //  echo "URL INSEGURO=> " . $url . "<br>";

        $_send_status .= "URL Inseguro";
    }
    //Verify if have protocol http or https in the url
    else if (
        !preg_match('/^http|https/', $url) && !preg_match('/(:[0-9])\w+/', $url)
    ) {
        //   echo "Protocolo no especificado=> " . $url . "<br>";
        $_send_status .= "Protocolo no especificado";
    }
    //verify if url don't have correct structure as ://, http, https, :, .host or white spaces
    else if (
        !preg_match('/[a-z]+\.+[a-z]*/', $url) && !preg_match('/(:[0-9])\w+/', $url) ||
        !str_contains($url, '://') ||
        str_contains($url, ' ') ||
        preg_match('/^http|https/', $url) &&
        !str_contains($url, '//') &&
        !preg_match('/(:[0-9])\w+/', $url)
    ) {
        //echo "Enlace malformado=> " . $url . "<br>";
        $_send_status .= "Enlace malformado";
    }
    //Verify if is a local host or have numbers with port in url like localhost:8080, 127....:8080 or something like this
    else if (str_contains($url, 'localhost') || preg_match('/(:[0-9])\w+/', $url)) {
        // echo "Enlace localhost=> " . $url . "<br>";

        $_send_status .= "URL de localhost invalido";
    }

    //If arrive here means that the url is correct
    else {
        // echo "URL FORMATO CORRECTO=> " . $url . "<br>";

    }

    //  echo "<br>" . $_send_url . "<br>" . $_send_status . "<br>" .  '<a href="' . get_post_permalink($_send_origin) . '" target="_blank">' . get_the_title($_send_origin) . '</a>' . "<br>";

    //Add <a></a> tag to title of origin post that visualize in Broken Links List table in admin dashboard
    $origin_post_id = $_send_origin;
    $_send_origin = '<a href="' . get_post_permalink($_send_origin) . '" target="_blank">' . get_the_title($_send_origin) . '</a>';


    //SAVE URL IN DATABASE 
    save_broken_links($_send_url, $_send_status, $_send_origin, $origin_post_id);
}

/**
 * SAVE Broken URLs in DATABASE
 *
 * @param [type] $_send_url
 * @param [type] $_send_status
 * @param [type] $_send_origin
 * @return void
 */
function save_broken_links($_send_url, $_send_status, $_send_origin, $origin_post_id)
{
    global $wpdb;

    //Save url, status code and origin in database
    $data = array('url' =>  $_send_url, 'status' => $_send_status, 'origin' => $_send_origin, 'origin_post_id' => $origin_post_id);
    $table = $wpdb->prefix . 'wp_broken_links_list';

    $format = array('%s', '%s', '%s');
    $wpdb->insert($table, $data, $format);
    $my_id = $wpdb->insert_id;
}

/**
 * DELETE old urls from DATABASE
 *
 * @return void
 */
function delete_broken_old_uls()
{
    global $wpdb;

    //Get current broken links in database
    $current_broken_urls = $wpdb->get_results(
        "SELECT id,url,status,origin from {$wpdb->prefix}wp_broken_links_list",
        ARRAY_A
    );

    //Delete all old broken links from the database
    foreach ($current_broken_urls as $old_url) {
        $wpdb->delete(
            $wpdb->prefix . 'wp_broken_links_list',
            ['id' => $old_url['id']],
            ['%d'],
        );
    }
}





/**
 * Adding Broken Links List page to Admin menu
 * 
 */
function add_bll_menu_to_admin_menu()
{
    //add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url,$position )

    add_menu_page(
        'Broken Links List',
        'Broken Links List',
        'activate_plugins',
        'broken_links_list',
        'broken_links_list_init',
        'dashicons-editor-unlink',
        21
    );
}
add_action('admin_menu', 'add_bll_menu_to_admin_menu');


/**
 * Broken Links List Page init callback
 *
 * @return void
 */
function broken_links_list_init()
{

    // Creating an instance
    $bllTable = new Broken_Links_List(); 
    
    echo '<div class="wrap"><h2>Broken Links List</h2>';
    // Prepare table
    $bllTable->prepare_items();
    // Display table
    $bllTable->display();
    echo '</div>';
}
