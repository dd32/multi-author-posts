import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	TextControl,
	TextHighlight,
	ToggleControl,
	Spinner,
	Notice,
	SearchControl,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';

import '../editor.scss';

const NAMESPACE = '/multi-author-posts/v1';

/**
 * A single co-author row.
 * @param root0
 * @param root0.author
 * @param root0.canManage
 * @param root0.onRemove
 */
function AuthorCard( { author, canManage, onRemove } ) {
	return (
		<HStack className="map-author-card" justify="space-between">
			<HStack spacing={ 2 }>
				<img
					src={ author.avatar }
					alt={ author.name }
					className="map-author-avatar"
					width={ 36 }
					height={ 36 }
				/>
				<span className="map-author-name">{ author.name }</span>
			</HStack>
			{ canManage && (
				<Button
					variant="tertiary"
					isDestructive
					isSmall
					onClick={ () => onRemove( author.id ) }
					aria-label={
						/* translators: %s: author display name */
						sprintf(
							__( 'Remove %s', 'multi-author-posts' ),
							author.name
						)
					}
				>
					{ __( 'Remove', 'multi-author-posts' ) }
				</Button>
			) }
		</HStack>
	);
}

/**
 * User search + direct-add UI (shown only when the current user can manage co-authors).
 * @param root0
 * @param root0.postId
 * @param root0.onAdd
 */
function DirectAdd( { postId, onAdd } ) {
	const [ search, setSearch ] = useState( '' );
	const [ suggestions, setSuggestions ] = useState( [] );
	const [ isSearching, setIsSearching ] = useState( false );

	useEffect( () => {
		if ( search.length < 2 ) {
			setSuggestions( [] );
			setIsSearching( false );
			return;
		}
		setIsSearching( true );
		const controller = new AbortController();
		apiFetch( {
			path: `${ NAMESPACE }/posts/${ postId }/suggested-authors?search=${ encodeURIComponent(
				search
			) }`,
			signal: controller.signal,
		} )
			.then( ( data ) => {
				setSuggestions( data || [] );
				setIsSearching( false );
			} )
			.catch( ( err ) => {
				if ( err.name !== 'AbortError' ) {
					setIsSearching( false );
				}
			} );
		return () => controller.abort();
	}, [ search, postId ] );

	const handleAdd = ( user ) => {
		apiFetch( {
			path: `${ NAMESPACE }/posts/${ postId }/co-authors`,
			method: 'POST',
			data: { user_id: user.id },
		} ).then( ( updatedList ) => {
			onAdd( updatedList );
			setSearch( '' );
			setSuggestions( [] );
		} );
	};

	return (
		<VStack spacing={ 1 } className="map-direct-add">
			<SearchControl
				label={ __( 'Add existing author', 'multi-author-posts' ) }
				value={ search }
				onChange={ setSearch }
				placeholder={ __(
					'Search by name or email…',
					'multi-author-posts'
				) }
			/>
			{ isSearching && <Spinner /> }
			{ suggestions.length > 0 && (
				<ul className="map-suggestions">
					{ suggestions.map( ( user ) => (
						<li key={ user.id } className="map-suggestion-item">
							<HStack justify="space-between">
								<HStack spacing={ 2 }>
									<img
										src={ user.avatar }
										alt={ user.name }
										width={ 28 }
										height={ 28 }
									/>
									<span>
										<TextHighlight
											text={ user.name }
											highlight={ search }
										/>
									</span>
								</HStack>
								<Button
									variant="secondary"
									isSmall
									onClick={ () => handleAdd( user ) }
								>
									{ __( 'Add', 'multi-author-posts' ) }
								</Button>
							</HStack>
						</li>
					) ) }
				</ul>
			) }
			{ search.length >= 2 &&
				! isSearching &&
				suggestions.length === 0 && (
					<p className="map-no-suggestions">
						{ __(
							'No matching authors found.',
							'multi-author-posts'
						) }
					</p>
				) }
		</VStack>
	);
}

/**
 * Per-post co-author settings (currently just: allow editing after publish).
 *
 * Visible to anyone who can see the panel, but only editors/admins can toggle.
 * Other users see the read-only state via a disabled control.
 */
function SettingsSection( { postId } ) {
	const [ allowPostPublishEdit, setAllowPostPublishEdit ] = useState( false );
	const [ canEditSettings, setCanEditSettings ] = useState( false );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );

	useEffect( () => {
		apiFetch( { path: `${ NAMESPACE }/posts/${ postId }/settings` } )
			.then( ( data ) => {
				setAllowPostPublishEdit( !! data?.allow_post_publish_edit );
				setCanEditSettings( !! data?.can_edit_settings );
				setIsLoading( false );
			} )
			.catch( () => setIsLoading( false ) );
	}, [ postId ] );

	const handleToggle = useCallback(
		( next ) => {
			setAllowPostPublishEdit( next );
			setIsSaving( true );
			apiFetch( {
				path: `${ NAMESPACE }/posts/${ postId }/settings`,
				method: 'PUT',
				data: { allow_post_publish_edit: next },
			} )
				.then( ( data ) => {
					setAllowPostPublishEdit( !! data?.allow_post_publish_edit );
					setIsSaving( false );
				} )
				.catch( () => {
					// Revert on failure.
					setAllowPostPublishEdit( ! next );
					setIsSaving( false );
				} );
		},
		[ postId ]
	);

	if ( isLoading || ! canEditSettings ) return null;

	return (
		<VStack spacing={ 2 } className="map-settings-section">
			<ToggleControl
				label={ __(
					'Allow co-authors to edit after publish',
					'multi-author-posts'
				) }
				help={ __(
					'When off, co-authors lose edit access once the post is published. The post author and site editors keep full access either way.',
					'multi-author-posts'
				) }
				checked={ allowPostPublishEdit }
				disabled={ isSaving }
				onChange={ handleToggle }
				__nextHasNoMarginBottom
			/>
		</VStack>
	);
}

/**
 * Invite-link section (shown only when the current user can manage co-authors).
 * @param root0
 * @param root0.postId
 */
function InviteSection( { postId } ) {
	// Plaintext URL is only available in-memory, immediately after creation.
	const [ inviteUrl, setInviteUrl ] = useState( null );
	const [ isActive, setIsActive ] = useState( false );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ copied, setCopied ] = useState( false );

	useEffect( () => {
		apiFetch( { path: `${ NAMESPACE }/posts/${ postId }/invite` } )
			.then( ( data ) => {
				setIsActive( !! data?.active );
				setIsLoading( false );
			} )
			.catch( () => setIsLoading( false ) );
	}, [ postId ] );

	const handleGenerate = useCallback( () => {
		setIsLoading( true );
		apiFetch( {
			path: `${ NAMESPACE }/posts/${ postId }/invite`,
			method: 'POST',
		} ).then( ( data ) => {
			setInviteUrl( data?.invite_url ?? null );
			setIsActive( !! data?.invite_url );
			setIsLoading( false );
		} );
	}, [ postId ] );

	const handleRevoke = useCallback( () => {
		setIsLoading( true );
		apiFetch( {
			path: `${ NAMESPACE }/posts/${ postId }/invite`,
			method: 'DELETE',
		} ).then( () => {
			setInviteUrl( null );
			setIsActive( false );
			setIsLoading( false );
		} );
	}, [ postId ] );

	const handleCopy = useCallback( () => {
		if ( ! inviteUrl ) {
			return;
		}
		navigator.clipboard?.writeText( inviteUrl ).then( () => {
			setCopied( true );
			setTimeout( () => setCopied( false ), 2000 );
		} );
	}, [ inviteUrl ] );

	if ( isLoading ) {
		return <Spinner />;
	}

	return (
		<VStack spacing={ 2 } className="map-invite-section">
			<strong>
				{ __( 'Shared invite link', 'multi-author-posts' ) }
			</strong>
			<p className="map-invite-description">
				{ __(
					'Anyone with this link who is registered on the network can join as a co-author. The link is valid for 24 hours and is only shown once — copy it now.',
					'multi-author-posts'
				) }
			</p>
			{ inviteUrl && (
				<VStack spacing={ 2 }>
					<TextControl
						label={ __( 'Invite link', 'multi-author-posts' ) }
						value={ inviteUrl }
						readOnly
						onClick={ ( e ) => e.target.select() }
					/>
					<HStack justify="flex-start" spacing={ 2 }>
						<Button
							variant="secondary"
							onClick={ handleCopy }
							disabled={ copied }
						>
							{ copied
								? __( 'Copied!', 'multi-author-posts' )
								: __( 'Copy link', 'multi-author-posts' ) }
						</Button>
					</HStack>
				</VStack>
			) }
			{ ! inviteUrl && isActive && (
				<p className="map-invite-active">
					{ __(
						'An invite link is active. Regenerate to issue a new one (the previous link will stop working) or revoke it.',
						'multi-author-posts'
					) }
				</p>
			) }
			<HStack justify="flex-start" spacing={ 2 }>
				<Button variant="secondary" onClick={ handleGenerate }>
					{ isActive
						? __( 'Regenerate invite link', 'multi-author-posts' )
						: __( 'Generate invite link', 'multi-author-posts' ) }
				</Button>
				{ isActive && (
					<Button
						variant="tertiary"
						isDestructive
						onClick={ handleRevoke }
					>
						{ __( 'Revoke', 'multi-author-posts' ) }
					</Button>
				) }
			</HStack>
		</VStack>
	);
}

/**
 * Main plugin component — registers the "Co-Authors" document settings panel.
 */
export default function MultiAuthorPlugin() {
	const postId = useSelect( ( select ) =>
		select( editorStore ).getCurrentPostId()
	);
	const postAuthorId = useSelect( ( select ) =>
		select( editorStore ).getEditedPostAttribute( 'author' )
	);
	const currentUserId = useSelect(
		( select ) => select( coreStore ).getCurrentUser()?.id
	);

	const [ coAuthors, setCoAuthors ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	// Post author and any existing co-author share the same management trust.
	// The REST API enforces the same rule server-side.
	const canManage =
		!! currentUserId &&
		( currentUserId === postAuthorId ||
			coAuthors.some( ( a ) => a.id === currentUserId ) );

	useEffect( () => {
		if ( ! postId ) {
			return;
		}
		setIsLoading( true );
		apiFetch( { path: `${ NAMESPACE }/posts/${ postId }/co-authors` } )
			.then( ( data ) => {
				setCoAuthors( data || [] );
				setIsLoading( false );
			} )
			.catch( ( err ) => {
				setError(
					err?.message ??
						__( 'Could not load co-authors.', 'multi-author-posts' )
				);
				setIsLoading( false );
			} );
	}, [ postId ] );

	const handleRemove = useCallback(
		( userId ) => {
			apiFetch( {
				path: `${ NAMESPACE }/posts/${ postId }/co-authors/${ userId }`,
				method: 'DELETE',
			} )
				.then( () =>
					setCoAuthors( ( prev ) =>
						prev.filter( ( a ) => a.id !== userId )
					)
				)
				.catch( ( err ) =>
					setError(
						err?.message ??
							__(
								'Could not remove co-author.',
								'multi-author-posts'
							)
					)
				);
		},
		[ postId ]
	);

	if ( ! postId ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="multi-author-posts-panel"
			title={ __( 'Co-Authors', 'multi-author-posts' ) }
			icon="groups"
		>
			{ error && (
				<Notice
					status="error"
					isDismissible
					onRemove={ () => setError( null ) }
				>
					{ error }
				</Notice>
			) }

			{ isLoading ? (
				<Spinner />
			) : (
				<VStack spacing={ 4 }>
					{ coAuthors.length === 0 ? (
						<p className="map-empty">
							{ __( 'No co-authors yet.', 'multi-author-posts' ) }
						</p>
					) : (
						<VStack spacing={ 2 }>
							{ coAuthors.map( ( author ) => (
								<AuthorCard
									key={ author.id }
									author={ author }
									canManage={ canManage }
									onRemove={ handleRemove }
								/>
							) ) }
						</VStack>
					) }

					{ canManage && (
						<>
							<DirectAdd
								postId={ postId }
								onAdd={ setCoAuthors }
							/>
							<SettingsSection postId={ postId } />
							<InviteSection postId={ postId } />
						</>
					) }
				</VStack>
			) }
		</PluginDocumentSettingPanel>
	);
}
