export interface ComponentImage {
  id: number;
  url: string;
  caption: string | null;
}

export interface Component {
  id: number;
  name: string;
  description: string;
  route: string | null;
  images: ComponentImage[];
  created_at: string;
}

export interface ComponentPayload {
  name: string;
  description: string;
  route?: string | null;
}
