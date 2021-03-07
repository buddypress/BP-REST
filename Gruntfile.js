/**
 * Grunt setup for the BP REST API plugin.
 *
 * @package buddypress
 */

module.exports = function( grunt ) {
	require( 'matchdep' )
		.filterDev( ['grunt-*', '!grunt-legacy-util'] )
		.forEach( grunt.loadNpmTasks );

	grunt.util = require( 'grunt-legacy-util' );

	grunt.initConfig(
		{
			pkg: grunt.file.readJSON( 'package.json' ),
			checktextdomain: {
				options: {
					correct_domain: false,
					text_domain: ['buddypress'],
					keywords: [
						'__:1,2d',
						'_e:1,2d',
						'_x:1,2c,3d',
						'_n:1,2,4d',
						'_ex:1,2c,3d',
						'_nx:1,2,4c,5d',
						'esc_attr__:1,2d',
						'esc_attr_e:1,2d',
						'esc_attr_x:1,2c,3d',
						'esc_html__:1,2d',
						'esc_html_e:1,2d',
						'esc_html_x:1,2c,3d',
						'_n_noop:1,2,3d',
						'_nx_noop:1,2,3c,4d'
					]
				},
				files: {
					src: ['**/*.php', '!**/node_modules/**'],
					expand: true
				}
			}
		}
	);

	// Default task.
	grunt.registerTask( 'default', ['checktextdomain'] );
};
