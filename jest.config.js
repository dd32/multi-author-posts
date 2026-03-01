/**
 * Extend the default @wordpress/scripts Jest configuration to add
 * @testing-library/jest-dom matchers for all unit tests.
 */
const wpJestConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...wpJestConfig,
	setupFilesAfterEnv: [
		...( wpJestConfig.setupFilesAfterEnv ?? [] ),
		'<rootDir>/tests/js/jest.setup.js',
	],
};
