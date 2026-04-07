module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	rules: {
		// @wordpress/* packages are provided by WordPress core at runtime and
		// externalized by @wordpress/scripts webpack. They are not installed as
		// npm packages, so the module resolver cannot find them.
		'import/no-unresolved': [ 'error', { ignore: [ '^@wordpress/' ] } ],
	},
};
