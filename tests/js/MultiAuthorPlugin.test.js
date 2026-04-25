/**
 * Unit tests for MultiAuthorPlugin component.
 *
 * Modules that call into WordPress APIs are mocked so the component
 * can be tested without a running WordPress instance.
 */

import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import MultiAuthorPlugin from '../../src/components/MultiAuthorPlugin';

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '@wordpress/data' );
jest.mock( '@wordpress/editor', () => ( {
	PluginDocumentSettingPanel: ( { children, title } ) => (
		<section>
			<h2>{ title }</h2>
			{ children }
		</section>
	),
	store: 'core/editor',
} ) );
jest.mock( '@wordpress/core-data', () => ( { store: 'core' } ) );

const apiFetch = require( '@wordpress/api-fetch' );
const { useSelect } = require( '@wordpress/data' );

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const POST_ID = 42;
const AUTHOR_ID = 1;
const CO_AUTHOR = { id: 2, name: 'Jane Doe', avatar: 'http://example.com/avatar.jpg' };

function setupUseSelect( { currentUserId = AUTHOR_ID, postAuthorId = AUTHOR_ID } = {} ) {
	useSelect.mockImplementation( ( selector ) =>
		selector( ( storeName ) => {
			const map = {
				'core/editor': {
					getCurrentPostId: () => POST_ID,
					getEditedPostAttribute: ( attr ) =>
						attr === 'author' ? postAuthorId : undefined,
				},
				core: {
					getCurrentUser: () => ( { id: currentUserId } ),
				},
			};
			return map[ storeName ] || {};
		} )
	);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe( 'MultiAuthorPlugin', () => {
	beforeEach( () => {
		setupUseSelect();
		// Default: empty co-authors list, no invite URL, settings not editable.
		apiFetch.mockImplementation( ( { path } ) => {
			if ( path.includes( '/invite' ) ) {
				return Promise.resolve( { active: false } );
			}
			if ( path.includes( '/settings' ) ) {
				return Promise.resolve( {
					allow_post_publish_edit: false,
					can_edit_settings: false,
				} );
			}
			return Promise.resolve( [] );
		} );
	} );

	afterEach( () => jest.clearAllMocks() );

	it( 'renders the Co-Authors panel heading', async () => {
		render( <MultiAuthorPlugin /> );
		await waitFor( () =>
			expect( screen.getByRole( 'heading', { name: /co-authors/i } ) ).toBeInTheDocument()
		);
	} );

	it( 'shows "No co-authors yet" when the list is empty', async () => {
		render( <MultiAuthorPlugin /> );
		await waitFor( () =>
			expect( screen.getByText( /no co-authors yet/i ) ).toBeInTheDocument()
		);
	} );

	it( 'renders co-author names after loading', async () => {
		apiFetch.mockImplementation( ( { path } ) => {
			if ( path.includes( '/invite' ) ) return Promise.resolve( { active: false } );
			return Promise.resolve( [ CO_AUTHOR ] );
		} );

		render( <MultiAuthorPlugin /> );
		await waitFor( () =>
			expect( screen.getByText( 'Jane Doe' ) ).toBeInTheDocument()
		);
	} );

	it( 'shows "Generate invite link" button when the author has no active invite', async () => {
		render( <MultiAuthorPlugin /> );
		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: /generate invite link/i } )
			).toBeInTheDocument()
		);
	} );

	it( 'shows "active" state with Regenerate / Revoke when invite exists', async () => {
		apiFetch.mockImplementation( ( { path } ) => {
			if ( path.includes( '/invite' ) ) {
				return Promise.resolve( { active: true, created: 1000, expires: 2000 } );
			}
			return Promise.resolve( [] );
		} );

		render( <MultiAuthorPlugin /> );
		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: /regenerate invite link/i } )
			).toBeInTheDocument()
		);
		expect( screen.getByRole( 'button', { name: /revoke/i } ) ).toBeInTheDocument();
	} );

	it( 'reveals the plaintext URL exactly once, after generate', async () => {
		apiFetch.mockImplementation( ( { path, method } ) => {
			if ( path.includes( '/invite' ) && method === 'POST' ) {
				return Promise.resolve( { invite_url: 'http://example.com/?map_invite=plain' } );
			}
			if ( path.includes( '/invite' ) ) {
				return Promise.resolve( { active: false } );
			}
			return Promise.resolve( [] );
		} );

		const user = userEvent.setup();
		render( <MultiAuthorPlugin /> );

		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: /generate invite link/i } )
			).toBeInTheDocument()
		);

		await act( async () => {
			await user.click( screen.getByRole( 'button', { name: /generate invite link/i } ) );
		} );

		expect(
			screen.getByDisplayValue( 'http://example.com/?map_invite=plain' )
		).toBeInTheDocument();
	} );

	it( 'hides management controls for an unrelated logged-in user', async () => {
		setupUseSelect( { currentUserId: 99, postAuthorId: AUTHOR_ID } );

		render( <MultiAuthorPlugin /> );
		await waitFor( () =>
			// Co-authors list loads
			expect( screen.queryByText( /no co-authors yet/i ) ).toBeInTheDocument()
		);

		expect( screen.queryByRole( 'button', { name: /generate invite link/i } ) ).toBeNull();
		expect( screen.queryByRole( 'searchbox' ) ).toBeNull();
	} );

	it( 'shows management controls to a co-author of the post', async () => {
		const CO_AUTHOR_ID = 99;
		setupUseSelect( { currentUserId: CO_AUTHOR_ID, postAuthorId: AUTHOR_ID } );
		apiFetch.mockImplementation( ( { path } ) => {
			if ( path.includes( '/invite' ) ) return Promise.resolve( { active: false } );
			return Promise.resolve( [ { id: CO_AUTHOR_ID, name: 'Me', avatar: 'x' } ] );
		} );

		render( <MultiAuthorPlugin /> );
		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: /generate invite link/i } )
			).toBeInTheDocument()
		);
		expect( screen.getByRole( 'searchbox' ) ).toBeInTheDocument();
	} );

	it( 'hides the post-publish-edit toggle when current user cannot edit settings', async () => {
		// Default mock returns can_edit_settings: false.
		render( <MultiAuthorPlugin /> );
		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: /generate invite link/i } )
			).toBeInTheDocument()
		);

		expect(
			screen.queryByRole( 'checkbox', { name: /allow co-authors to edit after publish/i } )
		).toBeNull();
	} );

	it( 'shows the post-publish-edit toggle to editors and reflects the current value', async () => {
		apiFetch.mockImplementation( ( { path } ) => {
			if ( path.includes( '/invite' ) ) return Promise.resolve( { active: false } );
			if ( path.includes( '/settings' ) ) {
				return Promise.resolve( {
					allow_post_publish_edit: true,
					can_edit_settings: true,
				} );
			}
			return Promise.resolve( [] );
		} );

		render( <MultiAuthorPlugin /> );

		await waitFor( () =>
			expect(
				screen.getByRole( 'checkbox', { name: /allow co-authors to edit after publish/i } )
			).toBeChecked()
		);
	} );

	it( 'persists the toggle change via PUT /settings', async () => {
		apiFetch.mockImplementation( ( { path, method } ) => {
			if ( path.includes( '/invite' ) ) return Promise.resolve( { active: false } );
			if ( path.includes( '/settings' ) && method === 'PUT' ) {
				return Promise.resolve( {
					allow_post_publish_edit: true,
					can_edit_settings: true,
				} );
			}
			if ( path.includes( '/settings' ) ) {
				return Promise.resolve( {
					allow_post_publish_edit: false,
					can_edit_settings: true,
				} );
			}
			return Promise.resolve( [] );
		} );

		const user = userEvent.setup();
		render( <MultiAuthorPlugin /> );

		await waitFor( () =>
			expect(
				screen.getByRole( 'checkbox', { name: /allow co-authors to edit after publish/i } )
			).not.toBeChecked()
		);

		await act( async () => {
			await user.click(
				screen.getByRole( 'checkbox', { name: /allow co-authors to edit after publish/i } )
			);
		} );

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'PUT',
				path: expect.stringContaining( '/settings' ),
				data: { allow_post_publish_edit: true },
			} )
		);
	} );

	it( 'calls DELETE when Remove button is clicked', async () => {
		apiFetch.mockImplementation( ( { path, method } ) => {
			if ( method === 'DELETE' ) return Promise.resolve( {} );
			if ( path.includes( '/invite' ) ) return Promise.resolve( { active: false } );
			return Promise.resolve( [ CO_AUTHOR ] );
		} );

		const user = userEvent.setup();
		render( <MultiAuthorPlugin /> );

		await waitFor( () =>
			expect( screen.getByText( 'Jane Doe' ) ).toBeInTheDocument()
		);

		await act( async () => {
			await user.click( screen.getByRole( 'button', { name: /remove jane doe/i } ) );
		} );

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'DELETE',
				path: expect.stringContaining( `/co-authors/${ CO_AUTHOR.id }` ),
			} )
		);
	} );
} );
