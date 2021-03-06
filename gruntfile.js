const sass = require('node-sass');

module.exports = function (grunt) {

	const config = {
		sass: {
			options: {
				implementation: sass,
				sourceMap: true
			},
			dist: {
				files: {
					'dist/themes/default/css/styles.css': 'src/themes/default/sass/index.scss',
				}
			}
		},

		watch: {
			css: {
				files: ['**/*.scss'],
				tasks: ['sass']
			},
			src: {
				files: ['src/**'],
				tasks: ['sync']
			}
		},

		sync: {
			main: {
				files: [{
					cwd: 'src',
					src: [
						'**',
						'!**/*.scss', // sass files are built separately
						'!react/**',
						'!themes/default/sass/**'
					],
					dest: 'dist',
				}],
				verbose: true
			}
		},

		uglify: {
			installation_bundles: {
				files: {
					'dist/global/scripts/installation-bundle.js': [
						'src/global/scripts/jquery.js',
						'src/global/scripts/general.js',
						'src/global/scripts/rsv.js',
						'src/global/scripts/jquery-ui-1.8.6.custom.min.js'
					]
				}
			}
		},

		cssmin: {
			target: {
				files: {
					'dist/themes/default/css/installation-bundle.css': [
						'src/themes/default/css/smoothness/jquery-ui-1.8.6.custom.css',
						'dist/themes/default/css/styles.css'
					]
				}
			}
		},

		run: {
			webpack_dev: {
				cmd: 'npm',
				args: [
					'start'
				]
			},
			webpack_prod: {
				cmd: 'npm',
				args: [
					'build'
				]
			}
		},

		concurrent: {
			watchers: {
				options: {
					logConcurrentOutput: true
				},
				tasks: ['watch', 'run:webpack_dev']
			}
		}
	};

	grunt.initConfig(config);

	require('load-grunt-tasks')(grunt);

	grunt.registerTask('default', ['sync', 'sass', 'concurrent:watchers']);

	// builds everything in the dist folder
	grunt.registerTask('prod', ['sync', 'sass', 'run:webpack_prod']);
};
