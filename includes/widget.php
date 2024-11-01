<?php
/**
 * Plugin Widgets Handler
 *
 * @author Justin Greer <justin@dash10.digital>
 * @package User Wallet Credit System
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Adds Foo_Widget widget.
 */
class UW_Credit_System_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			'uwcs_widget', // Base ID
			esc_html__( 'User Wallet Balance', 'uwcs' ), // Name
			array( 'description' => esc_html__( 'Display the balance of a logged in user. If the user is not logged in, this widget will not display.', 'uwcs' ), ) // Args
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		if ( is_user_logged_in() ) {
			echo $args['before_widget'];

			if ( ! empty( $instance['title'] ) ) {
				echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
			}

			$wallet_balance = wc_price( get_user_meta( get_current_user_id(), '_uw_balance', true ) );
			echo '<h4 class="uwcs_widget_balance">' . $wallet_balance . '</h4>';

			do_action( 'uwcs_balance_widget' );

			echo $args['after_widget'];
		}
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : esc_html__( 'New title', 'text_domain' );
		?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Title:', 'text_domain' ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
                   value="<?php echo esc_attr( $title ); ?>">
        </p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';

		return $instance;
	}

}

function register_uwcs_balance_widget() {
	register_widget( 'UW_Credit_System_Widget' );
}

add_action( 'widgets_init', 'register_uwcs_balance_widget' );