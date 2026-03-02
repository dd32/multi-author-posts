import { registerPlugin } from '@wordpress/plugins';
import MultiAuthorPlugin from './components/MultiAuthorPlugin';

registerPlugin( 'multi-author-posts', {
	render: MultiAuthorPlugin,
	icon: 'groups',
} );
