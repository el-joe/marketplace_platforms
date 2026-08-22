"use client";
import { IProductDetails } from "./types";
import useLocale from "@/src/hooks/use-locale";
import { cn } from "@/src/lib/utils";
import Image from "next/image";
import { useRouter } from "@/i18n/navigation";

type Props = {
  variantsData: IProductDetails["product_attributes"];
};

export default function Variants({ variantsData }: Props) {
  return (
    <div className="flex flex-col gap-3">
      {variantsData.map((variantList) => (
        <VariantOptionsList key={variantList.attribute_id} list={variantList} />
      ))}
    </div>
  );
}

function VariantOptionsList({
  list,
}: {
  list: IProductDetails["product_attributes"][0];
}) {
  const locale = useLocale();
  return (
    <>
      <h5 className="text-gray font-semibold mb-3 uppercase">
        {list.name[locale]}
      </h5>
      <div className="flex items-center flex-wrap gap-3">
        {list.values.map((value) => (
          <VariantCard key={value.attribute_value_id} variantData={value} />
        ))}
      </div>
      <div className="lg:hidden"></div>
    </>
  );
}

function VariantCard({
  variantData,
}: {
  variantData: IProductDetails["product_attributes"][0]["values"][0];
}) {
  const router = useRouter();
  const locale = useLocale();
  //   if (!!variantData?.image) {
  if (false) {
    return (
      <div
        className={cn(
          `p-3 rounded-md border border-border cursor-pointer transition-all hover:border-black`,
          true ? "hover:border-black" : "opacity-35 line-through",
        )}
        onClick={() => {
          if (!variantData.disabled)
            router.push(`/products/${variantData.url_param}`);
        }}
      >
        <Image
          className="max-h-40"
          //   src={variantData?.image}
          src={""}
          alt="color image"
          width={80}
          height={160}
        />
        <p className="text-gray text-sm text-center">
          {variantData.value[locale]}
        </p>
      </div>
    );
  }

  return (
    <div
      className={cn(
        `py-1 px-3 lg:py-3 lg:px-6 text-sm lg:text-base rounded-md border border-border cursor-pointer transition-all`,
        !variantData.disabled
          ? "hover:border-black"
          : "opacity-35 line-through",
        variantData.selected && "border-black",
      )}
      onClick={() => {
        if (!variantData.disabled)
          router.push(`/products/${variantData.url_param}`);
      }}
    >
      {!!variantData?.color_hex && (
        <div
          className={cn("w-6 h-6 mx-auto border rounded-full")}
          style={{ background: variantData.color_hex }}
        ></div>
      )}
      {variantData.value[locale]}
    </div>
  );
}
