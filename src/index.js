import { registerPlugin } from '@wordpress/plugins';
import CoAuthorsPlugin from './components/CoAuthorsPlugin';

registerPlugin( 'multi-author-posts', {
	render: CoAuthorsPlugin,
	icon: 'groups',
} );
