import { getHomeService } from "./api/get";
import { DynamicLayout } from "./dynamic-layout";

export default async function Home() {
  const homeData = await getHomeService();
  return (
    <div className="bg-white">
      {homeData.data.page_builder.sections.map((e) => (
        <DynamicLayout key={e.id} section={e} />
      ))}
    </div>
  );
}
