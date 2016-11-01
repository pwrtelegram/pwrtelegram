-- Taken from samus aran (https://github.com/LucentW/s-uzzbot)

args = {}
index = 1 
for value in string.gmatch(arg[1],"[^%s]+") do args [index] = value index = index + 1 end 
--inspect = require('inspect')

ID = args[1] -- It can be a part of contacts na


local function returnids(cb_extra, success, result)

  local receiver = cb_extra.receiver

  local chat_id = result.peer_id

  local chatname = result.print_name



  local text = ' IDs for chat '..chatname

  ..' ('..chat_id..')\n'

  ..'There are '..result.members_num..' members'

  ..'\n---------\n'

  i = 0

  for k,v in pairs(result.members) do

    i = i+1

    text = text .. i .. ". " .. string.gsub(v.print_name, "_", " ") .. " (" .. v.peer_id .. ")\n"

  end
  print(text)
--  send_large_msg(receiver, text)

end



local function returnidschan(cb_extra, success, result)

  local receiver = cb_extra.receiver

  local chat_id = cb_extra.peer_id

  local chatname = cb_extra.print_name



  local text = ' IDs for chat '..chatname

  ..' ('..string.gsub(chat_id, "channel#id", "")..')\n'

  ..'\n---------\n'

  i = 0

  for k,v in pairs(result) do

    i = i+1

    if v.print_name ~= nil then

      text = text .. i .. ". " .. string.gsub(v.print_name, "_", " ") .. " (" .. v.peer_id .. ")\n"

    else

      text = text .. i .. ". " .. "?" .. " (" .. v.peer_id .. ")\n"

    end

  end
  print(text)
--  send_large_msg(receiver, text)

end
function stringstarts(String, Start)
   return string.sub(String,1,string.len(Start))==Start
end
local function run(ID)
        if stringstarts(ID, "chat#id") then

          return chat_info(chat, returnids)

        end

        if stringstarts(ID, "channel#id") then

          return channel_get_users(chat, returnidschan)

        end

        return " Invalid ID."

end

run(ID)
