/**
 * Vitest configuration for the front end scripts.
 *
 * Excluded from the SVN deploy, so this never ships to users.
 */
import { defineConfig } from 'vitest/config';

export default defineConfig( {
	test: {
		environment: 'jsdom',
		include: [ 'tests/js/**/*.test.js' ],
	},
} );
