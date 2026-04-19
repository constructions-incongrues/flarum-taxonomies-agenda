import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexPage from 'flarum/forum/components/IndexPage';
import LinkButton from 'flarum/common/components/LinkButton';
import AgendaPage from './components/AgendaPage';
import EventComposer from './components/EventComposer';

app.initializers.add('mi-agenda-timeline', () => {
  app.routes['agenda'] = { path: '/agenda', component: AgendaPage };
  app.routes['agenda.new'] = { path: '/agenda/new', component: AgendaPage };

  // EventComposer is loaded directly via app.composer.load(EventComposer, ...)
  // in AgendaPage, so we don't need to register it here.

  extend(IndexPage.prototype, 'navItems', function (items) {
    items.add(
      'agenda',
      LinkButton.component(
        { href: app.route('agenda'), icon: 'fas fa-calendar-alt' },
        app.translator.trans('mi-agenda-timeline.forum.agenda.nav_item')
      ),
      85
    );
  });
});
