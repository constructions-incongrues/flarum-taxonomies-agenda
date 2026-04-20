export interface AgendaFacetsAttributes {
  villes: string[];
  lieux: string[];
  artistes: string[];
}

export interface AgendaFacetsResponse {
  data: {
    type: 'agenda-facets';
    id: 'global';
    attributes: AgendaFacetsAttributes;
  };
}
