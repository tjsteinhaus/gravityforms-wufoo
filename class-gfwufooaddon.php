<?php

GFForms::include_feed_addon_framework();

class GFWufooAddOn extends GFFeedAddOn {

    protected $_version = GF_WUFOO_ADDON_VERSION;
    protected $_min_gravityforms_version = '1.9';
    protected $_slug = 'wufooaddon';
    protected $_path = 'gravityforms-wufoo/wufooaddon.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Gravity Forms Wufoo Add-On';
    protected $_short_title = 'Wufoo Add-On';

    private static $_instance = null;

    /**
     * Get an instance of this class.
     *
     * @return GFSimpleFeedAddOn
     */
    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new GFWufooAddOn();
        }

        return self::$_instance;
    }

    /**
     * Plugin starting point. Handles hooks, loading of language files and PayPal delayed payment support.
     */
    public function init() {

        parent::init();

    }


    // # FEED PROCESSING -----------------------------------------------------------------------------------------------

    /**
     * Process the feed e.g. subscribe the user to a list.
     *
     * @param array $feed The feed object to be processed.
     * @param array $entry The entry object currently being processed.
     * @param array $form The form object currently being processed.
     *
     * @return bool|void
     */
    public function process_feed( $feed, $entry, $form ) {
        // Retrieve the name => value pairs for all fields mapped in the 'mappedFields' field map.
        $field_map = $this->get_field_map_fields( $feed, 'fieldMap' );

        // Loop through the fields from the field map setting building an array of values to be passed to the third-party service.
        $merge_vars = array();
        foreach( $field_map as $name => $field_id ) {

            // Get the field value for the specified field id
            $merge_vars[$name] = $this->get_field_value( $form, $entry, $field_id );

        }

        // Send the values to the third-party service.
        $wufoo = $this->connect_to_wufoo();
        $form_fields = (array) $wufoo->getFields( $feed['meta']['form_hash'] );
        $form_fields = $form_fields['Fields'];
        $post_fields = array();
        // Loop through all form fields that were submitted
        foreach( $merge_vars as $fieldID => $fieldValue ) {
            // Check to see if we have a date field and then fix formatting
            if( @$form_fields[$fieldID]->Type == 'date' ) {
                $fieldValue = str_replace( '-', '', $fieldValue );
            } elseif( @$form_fields[$fieldID]->Type == 'time' ) {
                $fieldValue = date( "H:i", strtotime( $fieldValue ) ).':00';
            }
            $post_fields[] = new \WufooSubmitField( $fieldID, esc_attr( $fieldValue ) );
        }

        // Sends data to Wufoo for submission
        $submitToWufoo = $wufoo->entryPost( $feed['meta']['form_hash'], $post_fields );
    }

    /**
     * Custom format the phone type field values before they are returned by $this->get_field_value().
     *
     * @param array $entry The Entry currently being processed.
     * @param string $field_id The ID of the Field currently being processed.
     * @param GF_Field_Phone $field The Field currently being processed.
     *
     * @return string
     */
    public function get_phone_field_value( $entry, $field_id, $field ) {

        // Get the field value from the Entry Object.
        $field_value = rgar( $entry, $field_id );

        // If there is a value and the field phoneFormat setting is set to standard reformat the value.
        if ( ! empty( $field_value ) && $field->phoneFormat == 'standard' && preg_match( '/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $field_value, $matches ) ) {
            $field_value = sprintf( '%s-%s-%s', $matches[1], $matches[2], $matches[3] );
        }

        return $field_value;
    }

    // # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

    /**
     * Configures the settings which should be rendered on the add-on settings tab.
     *
     * @return array
     */
    public function plugin_settings_fields() {
        return array(
            array(
                'title'  => esc_html__( 'Wufoo Forms Setup', 'wufooaddon' ),
                'fields' => array(
                    array(
                        'name'    => 'wufoo_apikey',
                        'tooltip' => esc_html__( 'You can find this in your Wufoo Forms account', 'wufooaddon' ),
                        'label'   => esc_html__( 'Wufoo API Key', 'wufooaddon' ),
                        'type'    => 'text',
                        'class'   => 'medium',
                    ),
                    array(
                        'name'    => 'wufoo_subdomain',
                        'tooltip' => esc_html__( 'You can find this in your Wufoo Forms account', 'wufooaddon' ),
                        'label'   => esc_html__( 'Wufoo Subdomain', 'wufooaddon' ),
                        'type'    => 'text',
                        'class'   => 'medium',
                    ),
                ),
            ),
        );
    }

    /**
     * Configures the settings which should be rendered on the feed edit page in the Form Settings > Simple Feed Add-On area.
     *
     * @return array
     */
    public function feed_settings_fields() {
        return array(
            array(
                'title'  => esc_html__( 'Wufoo Feed Settings', 'wufooaddon' ),
                'fields' => array(
                    array(
                        'label'   => esc_html__( 'Feed name', 'wufooaddon' ),
                        'type'    => 'text',
                        'name'    => 'feedName',
                        'tooltip' => esc_html__( 'This is the tooltip', 'wufooaddon' ),
                        'class'   => 'small',
                    ),
                    array(
                        'label'   => esc_html__( 'Form Hash', 'wufooaddon' ),
                        'type'    => 'text',
                        'name'    => 'form_hash',
                        'tooltip' => esc_html__( 'Identifies your form from Wufoo', 'wufooaddon' ),
                        'class'   => 'medium',
                    ),
                    array(
                        'name'      => 'fieldMap',
                        'label'     => esc_html__( 'Gravity Forms to Wufoo', 'wufooaddon' ),
                        'type'      => 'field_map',
                        'field_map' => $this->get_wufoo_fields()
                    ),
                ),
            ),
        );
    }

    public function get_wufoo_fields() {
        $wufoo = $this->connect_to_wufoo();
        $form_id = $this->get_setting( 'form_hash' );
        $fields = $wufoo->getFields( $form_id );
        $output = array();

        foreach( $fields->Fields as $key => $field ) {
            if( isset( $field->SubFields ) ) {
                foreach( $field->SubFields as $subfield ) {
                    $output[] = array(
                        'name' => $subfield->ID,
                        'label' => esc_html__( $field->Title . ' ('.$subfield->Label.')' ),
                        'required' => 0
                    );
                }
            } else {
                $output[] = array(
                    'name' => $key,
                    'label' => esc_html__($field->Title, 'wufooaddon'),
                    'required' => 0
                );
            }
        }

        return $output;
    }

    public function connect_to_wufoo() {
        $wufoo_api_key = $this->get_plugin_setting( 'wufoo_apikey' );
        $wufoo_subdomain = $this->get_plugin_setting( 'wufoo_subdomain' );
        $form_id = $this->get_setting( 'form_hash' );

        require_once( 'vendor/wufoo/WufooApiWrapper.php' );

        $wufoo = new \WufooApiWrapper( $wufoo_api_key, $wufoo_subdomain );

        return $wufoo;
    }

    /**
     * Configures which columns should be displayed on the feed list page.
     *
     * @return array
     */
    public function feed_list_columns() {
        return array(
            'feedName'  => esc_html__( 'Name', 'wufooaddon' ),
        );
    }

    /**
     * Prevent feeds being listed or created if an api key isn't valid.
     *
     * @return bool
     */
    public function can_create_feed() {

        // Get the plugin settings.
        $settings = $this->get_plugin_settings();

        // Access a specific setting e.g. an api key
        $apikey = rgar( $settings, 'wufoo_apikey' );
        $subdomain = rgar( $settings, 'wufoo_apikey' );

        if( !empty( $apikey ) && !empty( $subdomain ) ) {
            return true;
        } else {
            return false;
        }
    }

}