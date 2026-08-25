"use client";
import useCountDown from "@/src/hooks/useCountDown";
import { HourglassIcon } from "lucide-react";
import React from "react";

type Props = {
  targetDate: string;
  // theme?: "default";
};

const CountDown = ({ targetDate }: Props) => {
  const { D, H, M, S } = useCountDown(new Date(targetDate));
  return (
    <div className="bg-gray px-2 h-8 text-white flex items-center gap-0.5 text-xl w-fit font-bold">
      <HourglassIcon className="size-4" />
      <p>{!!Number(D) ? "+24" : H}:</p>
      <p>{M}:</p>
      <p>{S}</p>
    </div>
  );
};

export default CountDown;
