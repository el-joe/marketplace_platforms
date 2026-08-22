import { notFound } from "next/navigation";
import ReturnDetails from "@/src/features/noon/profile/returns/details";
import { getReturnByNumber } from "@/src/features/noon/profile/returns/api/returns.actions";

type Props = {
  params: Promise<{ id: string }>;
};

export default async function ReturnDetailsPage({ params }: Props) {
  const { id } = await params;
  const returnDetail = await getReturnByNumber(id);

  if (!returnDetail) notFound();

  return <ReturnDetails returnDetail={returnDetail} />;
}
