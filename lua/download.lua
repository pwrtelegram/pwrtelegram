#!/usr/bin/lua

args = {}
index = 1 
for value in string.gmatch(arg[1],"[^%s]+") do args [index] = value index = index + 1 end 
--inspect = require('inspect')

CONTACT_NAME = args[1] -- It can be a part of contacts na
FILE_ID = args[2]
TYPE = args[3]
MESSAGE_COUNT = 500
DONE = false

function dialogs_cb(extra, success, dialog)
 if success then
  for _,d in pairs(dialog) do
   v = d.peer
   if (v.username == CONTACT_NAME and v.peer_type == "user") then
    get_history(v.print_name, MESSAGE_COUNT, history_cb, history_extra)
   end
  end
 end 
end

function callback(extra, success, result)
  if success and result ~= false then
    print('{"event":"download", "result":"'..result..'"}')
  end
  os.exit()
end

function run(msg, type)
    if type == 'document' then
      load_document(msg, callback, msg)
    end
    if type == 'photo' then
      load_photo(msg, callback, msg)
    end
    if type == 'video' then
      load_video(msg, callback, msg)
    end
    if type == 'audio' then
      load_audio(msg, callback, msg)
    end
end

function history_cb(extra, success, history)
   if success then
      for _,m in pairs(history) do
         if not m.service and DONE == false then -- Ignore Telegram service messages
            local out = m.out and 1 or 0 -- Cast boolean to integer

            if (m.text == FILE_ID and m.reply_id ~= nil and m.from.username == CONTACT_NAME) then --
               run(m.reply_id, TYPE)
               DONE = true
            end
         end
      end
      print("done")
   end
end

function user_cb(extra, success, user)
end


function on_binlog_replay_end ()
 res = get_dialog_list(dialogs_cb, contacts_extra)
end

function on_msg_receive (msg)
-- print(inspect(msg))
end

function on_our_id (id)
end

function on_secret_chat_created (peer)
end

function on_user_update (user)
end

function on_chat_update (user)
end

function on_get_difference_end ()
end
