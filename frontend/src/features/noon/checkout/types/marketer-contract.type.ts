export interface IMarketerContract {
  version_id: string;
  version_number: number;
  title_en: string | null;
  title_ar: string | null;
  content_type: "pdf" | "text";
  file_url: string | null;
  text_content: string | null;
  is_required: boolean;
}

export interface IMarketerContractResponse {
  contract: IMarketerContract | null;
}

export interface IAcceptMarketerContractResponse {
  acceptance_id: string;
  accepted_at: string;
  already_accepted: boolean;
}
