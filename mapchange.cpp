// mapchange.cpp : Defines the entry point for the DLL application.
//

#include "bzfsAPI.h"
#include <math.h>
#include <time.h>
#include <fstream>
#include <string>
#include <list>

std::string confFile;
std::string outputFile;
bool matchInProgress = false;

class MapChangerCommands : public bz_CustomSlashCommandHandler
{
public:
    
  virtual bool SlashCommand ( int playerID, bz_ApiString command, bz_ApiString /*message*/, bz_APIStringList* params )
  {
    if (command == "maplist") {
      std::ifstream confStream(confFile.c_str());
      if (!confStream.fail()) {
        std::string line;

        bz_sendTextMessage(BZ_SERVER,playerID,"Available configurations: ");

        bz_APIStringList* lineList = bz_newStringList();
        while (std::getline(confStream, line))
        {
          lineList->clear();
          lineList->tokenize(line.c_str(), " \t", 2, true);

          if (lineList->size() == 2)
            bz_sendTextMessage(BZ_SERVER,playerID,(std::string(" -  ") + lineList->get(0).c_str()).c_str());
        }
        bz_deleteStringList(lineList);
      }
      return true;
    }

    bz_BasePlayerRecord *player = bz_getPlayerByIndex(playerID);
    if (!player)
      return true;

    if (player->hasPerm("confChange")) {
      if (!matchInProgress || player->hasPerm(bz_perm_shutdownServer)) {
        if (command == "maprandom") {
          std::ifstream confStream(confFile.c_str());
          std::string line;

          std::vector<std::string> mapnames;
          std::vector<std::string> mapfiles;
          bz_APIStringList* lineList = bz_newStringList();
          while (std::getline(confStream, line))
          {
            lineList->clear();
            lineList->tokenize(line.c_str(), " \t", 2, true);

            if (lineList->size() == 2) {
              mapnames.push_back(lineList->get(0).c_str());
              mapfiles.push_back(lineList->get(1).c_str());
            }
          }

          int i = rand() % mapnames.size();
          std::ofstream oputfStream(outputFile.c_str());
          oputfStream << mapfiles[i] << std::endl;
          oputfStream.close();
          bz_sendTextMessage(BZ_SERVER,BZ_ALLUSERS,(std::string("Server restarting with randomly selected configuration (") + mapnames[i] + "): Requested by " + player->callsign.c_str()).c_str());
          bz_shutdown();

          bz_deleteStringList(lineList);
          bz_freePlayerRecord(player);

          return true;
        }
        if (params->size() != 1) {
          bz_sendTextMessage(BZ_SERVER,playerID,"Usage: /mapchange <confname>");
          bz_freePlayerRecord(player);

          return true;
        }

        bool done = false;
        std::ifstream confStream(confFile.c_str());
        if (!confStream.fail()) {
          std::string line;

          bz_APIStringList* lineList = bz_newStringList();
          while (std::getline(confStream, line))
          {
            lineList->clear();
            lineList->tokenize(line.c_str(), " \t", 2, true);

            if (lineList->size() == 2 && lineList->get(0) == params->get(0)) {
              std::ofstream oputfStream(outputFile.c_str());
              oputfStream << lineList->get(1).c_str() << std::endl;
              oputfStream.close();
              bz_sendTextMessage(BZ_SERVER,BZ_ALLUSERS,(std::string("Server restarting with configuration ") + params->get(0).c_str() + ": Requested by " + player->callsign.c_str()).c_str());
              bz_shutdown();
              done = true;
            }
          }

          bz_deleteStringList(lineList);
        }
        if (!done) bz_sendTextMessage(BZ_SERVER,playerID,"The configuration you selected does not exist");
      }
      else {
        bz_sendTextMessage(BZ_SERVER,playerID,"Sorry, you are not allowed to change configurations when a match is in progress");
        bz_sendTextMessage(BZ_SERVER,playerID,"For this malicious activity, you will be kicked");
        bz_kickUser(playerID, "mapchange during match", true);
      }
    }
    else bz_sendTextMessage(BZ_SERVER,playerID,"Sorry, you are not allowed to change configurations on this server");

    bz_freePlayerRecord(player);

    return true;
  }
};

MapChangerCommands mapChanger;

class MapChanger : public bz_Plugin 
{
public:
  virtual void Event ( bz_EventData *eventData )
  {
    if (eventData->eventType == bz_eGameEndEvent) matchInProgress = false;
    else if (eventData->eventType == bz_eGameStartEvent) matchInProgress = true;
  }
  
  virtual const char* Name (){return "Mapchange";}
  virtual void Init ( const char* config);
  virtual void Cleanup();
};

BZ_PLUGIN(MapChanger);

void MapChanger::Init ( const char* commandLine )
{
  bz_debugMessage(4,"mapchange plugin loaded");
  bz_APIStringList* cmd = bz_newStringList();
  cmd->tokenize(commandLine, ",", 2, true);
  if (cmd->size() != 2) {
    bz_deleteStringList(cmd);
  }
  confFile = cmd->get(1).c_str();
  outputFile = cmd->get(0).c_str();
  bz_deleteStringList(cmd);
  Register(bz_eGameEndEvent);
  Register(bz_eGameStartEvent);
  bz_registerCustomSlashCommand ( "mapchange", &mapChanger );
  bz_registerCustomSlashCommand ( "maplist", &mapChanger );
  bz_registerCustomSlashCommand ( "maprandom", &mapChanger );
  srand(time(NULL));
}

void MapChanger::Cleanup ( void )
{
  Flush();
  bz_removeCustomSlashCommand ( "maprandom" );
  bz_removeCustomSlashCommand ( "maplist" );
  bz_removeCustomSlashCommand ( "mapchange" );
  bz_debugMessage(4,"mapchange plugin unloaded");
}

// Local Variables: ***
// mode:C++ ***
// tab-width: 8 ***
// c-basic-offset: 2 ***
// indent-tabs-mode: t ***
// End: ***
// ex: shiftwidth=2 tabstop=8

