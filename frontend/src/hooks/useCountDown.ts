import { useEffect, useState } from "react";

type TTargetDate = Date;

const useCountDown = (targetDate: TTargetDate) => {
  const [distanceTime, setDistanceTime] = useState({
    D: "-",
    H: "--",
    M: "--",
    S: "--",
  });
  useEffect(() => {
    // 1. Set the date we're counting down to
    const countDownDate = new Date(targetDate).getTime();

    // 2. Update the count down every 1 second
    const timer = setInterval(function () {
      // Get today's date and time
      const now = new Date().getTime();

      // Find the distance between now and the target date
      const distance = countDownDate - now;

      // 3. Time calculations for days, hours, minutes and seconds
      const days = Math.floor(distance / (1000 * 60 * 60 * 24));
      const hours = Math.floor(
        (distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60),
      );
      const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
      const seconds = Math.floor((distance % (1000 * 60)) / 1000);

      // 4.Set distance time
      setDistanceTime({
        D: days.toString(),
        H: hours < 10 ? `0${hours.toString()}` : hours.toString(),
        M: minutes < 10 ? `0${minutes.toString()}` : minutes.toString(),
        S: seconds < 10 ? `0${seconds.toString()}` : seconds.toString(),
      });

      // 5. If the count down is finished
      if (distance < 0) {
        clearInterval(timer);
        setDistanceTime({
          D: "0",
          H: "00",
          M: "00",
          S: "00",
        });
      }
    }, 1000);
    return () => {
      clearInterval(timer);
    };
  }, [targetDate]);
  return distanceTime;
};

export default useCountDown;
