import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { AgendaFiltersState } from '../utils/agendaApi';

interface AgendaFiltersAttrs {
  filters: AgendaFiltersState;
  villes: string[];
  onChange: (next: AgendaFiltersState) => void;
}

export default class AgendaFilters extends Component<AgendaFiltersAttrs> {
  view(): Mithril.Children {
    const { filters, villes, onChange } = this.attrs;

    const update = (patch: Partial<AgendaFiltersState>) => {
      const next: AgendaFiltersState = { ...filters, ...patch };
      (Object.keys(next) as (keyof AgendaFiltersState)[]).forEach((k) => {
        if (!next[k]) delete next[k];
      });
      onChange(next);
    };

    return (
      <div className="AgendaFilters">
        <label className="AgendaFilters-field">
          <span className="AgendaFilters-label">Du</span>
          <input
            type="date"
            className="FormControl"
            value={filters.from ?? ''}
            onchange={(e: Event) => update({ from: (e.target as HTMLInputElement).value })}
          />
        </label>
        <label className="AgendaFilters-field">
          <span className="AgendaFilters-label">Au</span>
          <input
            type="date"
            className="FormControl"
            value={filters.to ?? ''}
            onchange={(e: Event) => update({ to: (e.target as HTMLInputElement).value })}
          />
        </label>
        <label className="AgendaFilters-field">
          <span className="AgendaFilters-label">Ville</span>
          <select
            className="FormControl"
            oncreate={(vnode: any) => (vnode.dom.value = filters.ville ?? '')}
            onupdate={(vnode: any) => (vnode.dom.value = filters.ville ?? '')}
            onchange={(e: Event) => update({ ville: (e.target as HTMLSelectElement).value })}
          >
            <option value="">Toutes</option>
            {villes.map((o) => (
              <option value={o}>{o}</option>
            ))}
          </select>
        </label>
      </div>
    );
  }
}
