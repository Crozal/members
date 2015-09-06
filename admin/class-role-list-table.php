<?php
/**
 * Handles the roles table on the Roles admin screen.
 *
 * @package    Members
 * @subpackage Admin
 * @author     Justin Tadlock <justin@justintadlock.com>
 * @copyright  Copyright (c) 2009 - 2015, Justin Tadlock
 * @link       http://themehybrid.com/plugins/members
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/**
 * Role list table for the roles management page in the admin. Extends the core `WP_List_Table`
 * class in the admin.
 *
 * @since  1.0.0
 * @access public
 */
class Members_Role_List_Table extends WP_List_Table {

	/**
	 * The current view.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $role_view = 'all';

	/**
	 * Allowed role views.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    array
	 */
	public $allowed_role_views = array();

	/**
	 * The default role.  This will be assigned the value of `get_option( 'default_role' )`.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $default_role = 'subscriber';

	/**
	 * The current user object.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    object
	 */
	public $current_user = '';

	/**
	 * Sets up the list table.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function __construct() {

		$args = array(
			'plural'   => 'roles',
			'singular' => 'role',
		);

		parent::__construct( $args );

		// Get the current user object.
		$this->current_user = new WP_User( get_current_user_id() );

		// Get the defined default role.
		$this->default_role = get_option( 'default_role', $this->default_role );

		// Get the role views.
		$this->allowed_role_views = array_keys( $this->get_views() );

		// Get the current view.
		if ( isset( $_GET['role_view'] ) && in_array( $_GET['role_view'], $this->allowed_role_views ) )
			$this->role_view = $_GET['role_view'];
	}

	/**
	 * Sets up the items (roles) to list.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function prepare_items() {

		$roles = array();

		// Get the correct roles array based on the view.
		if ( 'mine' === $this->role_view )
			$roles = array_keys( members_get_user_role_names( $this->current_user->ID ) );

		elseif ( 'active' === $this->role_view )
			$roles = array_keys( members_get_active_role_names() );

		elseif ( 'inactive' === $this->role_view )
			$roles = array_keys( members_get_inactive_role_names() );

		elseif ( 'editable' === $this->role_view )
			$roles = array_keys( members_get_editable_role_names() );

		elseif ( 'uneditable' === $this->role_view )
			$roles = array_keys( members_get_uneditable_role_names() );

		elseif ( 'wordpress' === $this->role_view )
			$roles = array_keys( members_get_wordpress_role_names() );

		else
			$roles = array_keys( members_get_role_names() );

		$roles = apply_filters( 'members_manage_roles_items', $roles, $this->role_view );

		// Sort the roles if given something to sort by.
		if ( isset( $_GET['orderby'] ) && isset( $_GET['order'] ) ) {

			// Sort by title/role name, descending.
			if ( 'title' === $_GET['orderby'] && 'desc' === $_GET['order'] )
				arsort( $roles );

			// Sort by role, ascending.
			elseif ( 'role' === $_GET['orderby'] && 'asc' === $_GET['order'] )
				ksort( $roles );

			// Sort by role, descending.
			elseif ( 'role' === $_GET['orderby'] && 'desc' === $_GET['order'] )
				krsort( $roles );

			// Sort by title/role name, ascending.
			else
				asort( $roles );

		// Sort by title/role name, ascending.
		} else {
			asort( $roles );
		}

		// Get the per page option name.
		$option = $this->screen->get_option( 'per_page', 'option' );

		if ( ! $option )
			$option = str_replace( '-', '_', "{$this->screen->id}_per_page" );

		// Get the number of roles to show per page.
		$per_page = (int) get_user_option( $option );

		if ( ! $per_page || $per_page < 1 ) {

			$per_page = $this->screen->get_option( 'per_page', 'default' );

			if ( ! $per_page )
				$per_page = 20;
		}

		// Set up some current page variables.
		$current_page = $this->get_pagenum();
		$items        = $roles;
		$total_count  = count( $items );

		// Set the current page items.
		$this->items = array_slice( $items, ( $current_page - 1 ) * $per_page, $per_page );

		// Set the pagination arguments.
		$this->set_pagination_args( array( 'total_items' => $total_count, 'per_page' => $per_page ) );
	}

	/**
	 * Returns an array of columns to show.
	 *
	 * @see    members_manage_roles_columns()
	 * @since  1.0.0
	 * @access public
	 * @return array
	 */
	public function get_columns() {
		return get_column_headers( $this->screen );
	}

	/**
	 * The checkbox column callback.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  string     $role
	 * @return string
	 */
	protected function column_cb( $role ) {

		if ( $role == get_option( 'default_role' ) || in_array( $role, $this->current_user->roles ) || ! members_is_role_editable( $role ) )
			$out = '';

		else
			$out = sprintf( '<input type="checkbox" name="roles[%1$s]" value="%1$s" />', esc_attr( $role ) );

		return apply_filters( 'members_manage_roles_column_cb', $out, $role );
	}

	/**
	 * The role name column callback.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  string     $role
	 * @return string
	 */
	protected function column_title( $role ) {

		$states      = array();
		$role_states = '';

		// If the role is the default role.
		if ( $role == get_option( 'default_role' ) )
			$states[] = esc_html__( 'Default Role', 'members' );

		// If the current user has this role.
		if ( members_current_user_has_role( $role ) )
			$states[] = esc_html__( 'Your Role', 'members' );

		// Allow devs to filter the role states.
		$states = apply_filters( 'members_role_states', $states, $role );

		// If we have states, string them together.
		if ( ! empty( $states ) ) {

			foreach ( $states as $state )
				$role_states .= sprintf( '<span class="role-state">%s</span>', $state );

			$role_states = ' &ndash; ' . $role_states;
		}

		// Add the title and role states.
		$title = sprintf( '<strong><a class="row-title" href="%s">%s</a>%s</strong>', members_get_edit_role_url( $role ), members_get_role_name( $role ), $role_states );

		return apply_filters( 'members_manage_roles_column_role_name', $title, $role );
	}

	/**
	 * The role column callback.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  string     $role
	 * @return string
	 */
	protected function column_role( $role ) {
		return apply_filters( 'members_manage_roles_column_role', members_sanitize_role( $role ), $role );
	}

	/**
	 * The users column callback.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  string     $role
	 * @return string
	 */
	protected function column_users( $role ) {
		return apply_filters( 'members_manage_roles_column_users', members_get_role_user_count( $role ), $role );
	}

	/**
	 * The caps column callback.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  string     $role
	 * @return string
	 */
	protected function column_granted_caps( $role ) {
		return apply_filters( 'members_manage_roles_column_granted_caps', members_get_role_granted_cap_count( $role ), $role );
	}

	/**
	 * The caps column callback.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  string     $role
	 * @return string
	 */
	protected function column_denied_caps( $role ) {
		return apply_filters( 'members_manage_roles_column_denied_caps', members_get_role_denied_cap_count( $role ), $role );
	}

	/**
	 * Returns the name of the primary column.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return string
	 */
	protected function get_default_primary_column_name() {
		return 'title';
	}

	/**
	 * Handles the row actions.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  string     $role
	 * @param  string     $column_name
	 * @param  string     $primary
	 * @return array
	 */
	protected function handle_row_actions( $role, $column_name, $primary ) {

		$actions = array();

		// Only add row actions on the primary column (title/role name).
		if ( $primary === $column_name ) {

			// If the role can be edited.
			if ( members_is_role_editable( $role ) ) {

				// If the current user can edit the role, add an edit link.
				if ( current_user_can( 'edit_roles' ) )
					$actions['edit'] = sprintf( '<a href="%s">%s</a>', members_get_edit_role_url( $role ), esc_html__( 'Edit', 'members' ) );

				// If the current user can delete the role, add a delete link.
				if ( ( is_multisite() && is_super_admin() && $role !== $this->default_role ) || ( current_user_can( 'delete_roles' ) && $role !== $this->default_role && !current_user_can( $role ) ) )
					$actions['delete'] = sprintf( '<a class="members-delete-role-link" href="%s">%s</a>', members_get_delete_role_url( $role ), esc_html__( 'Delete', 'members' ) );

			// If the role cannot be edited.
			} else {

				// Add the view role link.
				$actions['view'] = sprintf( '<a href="%s">%s</a>', members_get_edit_role_url( $role ), esc_html__( 'View', 'members' ) );
			}

			// If the current user can create roles, add the clone role link.
			if ( current_user_can( 'create_roles' ) )
				$actions['clone'] = sprintf( '<a href="%s">%s</a>', members_get_clone_role_url( $role ), esc_html__( 'Clone', 'members' ) );

			// If this is the default role and the current user can manage options, add a default role change link.
			if ( current_user_can( 'manage_options' ) && $role === $this->default_role )
				$actions['default_role'] = sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php#default_role' ) ), esc_html__( 'Change Default', 'members' ) );

			// If the currrent user can view users, add a users link.
			if ( current_user_can( 'list_users' ) )
				$actions['users'] = sprintf( '<a href="%s">%s</a>', members_get_role_users_url( $role ), esc_html__( 'Users', 'members' ) );

			// Allow devs to filter the row actions.
			$actions = apply_filters( 'members_roles_row_actions', $actions, $role );
		}

		return $this->row_actions( $actions );
	}

	/**
	 * Returns an array of sortable columns.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return array
	 */
	protected function get_sortable_columns() {

		return array(
			'title' => array( 'title',  true  ),
			'role'  => array( 'role',   false ),
		);
	}

	/**
	 * Returns an array of views for the list table.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return array
	 */
	protected function get_views() {

		$views   = array();
		$current = ' class="current"';

		// Get the view URLs.
		$all_url        = members_get_edit_roles_url();
		$mine_url       = members_get_my_roles_url();
		$active_url     = members_get_active_roles_url();
		$inactive_url   = members_get_inactive_roles_url();
		$editable_url   = members_get_editable_roles_url();
		$uneditable_url = members_get_uneditable_roles_url();
		$wordpress_url  = members_get_wordpress_roles_url();

		// Get the view counts.
		$all_count        = count( members_get_role_names()            );
		$mine_count       = count( $this->current_user->roles          );
		$active_count     = count( members_get_active_role_names()     );
		$inactive_count   = count( members_get_inactive_role_names()   );
		$editable_count   = count( members_get_editable_role_names()   );
		$uneditable_count = count( members_get_uneditable_role_names() );
		$wordpress_count  = count( members_get_wordpress_role_names()  );

		// Set up an array of views.
		$_views = array(
			'all'        => array( 'url' => $all_url,        'label' => _n( 'All %s',        'All %s',        $all_count,        'members' ), 'count' => $all_count        ),
			'mine'       => array( 'url' => $mine_url,       'label' => _n( 'Mine %s',       'Mine %s',       $mine_count,       'members' ), 'count' => $mine_count       ),
			'active'     => array( 'url' => $active_url,     'label' => _n( 'Has Users %s',  'Has Users %s',  $active_count,     'members' ), 'count' => $active_count     ),
			'inactive'   => array( 'url' => $inactive_url,   'label' => _n( 'No Users %s',   'No Users %s',   $inactive_count,   'members' ), 'count' => $inactive_count   ),
			'editable'   => array( 'url' => $editable_url,   'label' => _n( 'Editable %s',   'Editable %s',   $editable_count,   'members' ), 'count' => $editable_count   ),
			'uneditable' => array( 'url' => $uneditable_url, 'label' => _n( 'Uneditable %s', 'Uneditable %s', $uneditable_count, 'members' ), 'count' => $uneditable_count ),
			'wordpress'  => array( 'url' => $wordpress_url,  'label' => _n( 'WordPress %s',  'WordPress %s',  $wordpress_count,  'members' ), 'count' => $wordpress_count  )
		);

		// Loop through the views and put them into an array of links.
		foreach ( $_views as $view => $view_args ) {

			// Skip any views with 0 roles.
			if ( 0 >= $view_args['count'] )
				continue;

			// Add the view link.
			$views[ $view ] = sprintf(
				'<a%s href="%s">%s</a>',
				$view === $this->role_view ? $current : '',
				$view_args['url'],
				sprintf( $view_args['label'], sprintf( '<span class="count">(%s)</span>', number_format_i18n( $view_args['count'] ) ) )
			);
		}

		return apply_filters( 'members_manage_roles_views', $views, $this->role_view, $all_url );
	}

	/**
	 * Displays the list table.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function display() {

		$this->views();

		parent::display();
	}

	/**
	 * Returns an array of bulk actions available.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions = array();

		if ( current_user_can( 'delete_roles' ) )
			$actions['delete'] = esc_html__( 'Delete', 'members' );

		return apply_filters( 'members_manage_roles_bulk_actions', $actions );
	}
}
