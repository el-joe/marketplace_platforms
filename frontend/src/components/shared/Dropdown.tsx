import React, { JSXElementConstructor } from "react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/src/components/ui/dropdown-menu";
import { MenuPopupProps, MenuPositionerProps } from "@base-ui/react";
type TItem = { itemLabel: string; value: string; itemIcon?: React.ReactNode };
type Props = {
  triggerButton: React.ReactElement<
    unknown,
    string | JSXElementConstructor<unknown>
  >;
  groupOFItems?: [
    {
      groupLabel: string;
      items: TItem[];
    },
  ];
  items?: TItem[];
  listTitle?: string;
  contentClasses?: string;
  menuProps?: MenuPopupProps &
    Pick<MenuPositionerProps, "align" | "alignOffset" | "side" | "sideOffset">;
  onSelect?: (item: TItem) => void;
};

const Dropdown = ({
  triggerButton,
  groupOFItems,
  items,
  listTitle,
  contentClasses,
  menuProps,
  onSelect = () => {},
}: Props) => {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger render={triggerButton} />
      <DropdownMenuContent className={contentClasses} {...menuProps}>
        {groupOFItems ? (
          <>
            {groupOFItems.map((group) => (
              <DropdownMenuGroup key={group.groupLabel}>
                <DropdownMenuLabel>{group.groupLabel}</DropdownMenuLabel>
                {group.items.map((item) => (
                  <DropdownMenuItem
                    key={item.value}
                    onClick={() => onSelect(item)}
                  >
                    {item.itemIcon}
                    {item.itemLabel}
                  </DropdownMenuItem>
                ))}
              </DropdownMenuGroup>
            ))}
          </>
        ) : (
          <DropdownMenuGroup>
            {listTitle && <DropdownMenuLabel>{listTitle}</DropdownMenuLabel>}
            {items?.map((item) => (
              <DropdownMenuItem key={item.value} onClick={() => onSelect(item)}>
                {item.itemIcon}
                {item.itemLabel}
              </DropdownMenuItem>
            ))}
          </DropdownMenuGroup>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
};

export default Dropdown;
